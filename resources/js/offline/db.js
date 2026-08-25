const DB_NAME = 'exam-offline';
const DB_VERSION = 2;

const STORES = {
    examinations: 'examinations',
    answers: 'answers',
    syncQueue: 'offline_sync_queue',
    timerState: 'timer_state',
    attemptSessions: 'attempt_sessions',
    meta: 'meta',
};

let dbPromise = null;

function openDb() {
    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = request.result;
            const oldVersion = event.oldVersion;

            if (oldVersion < 1) {
                if (!db.objectStoreNames.contains(STORES.examinations)) {
                    const store = db.createObjectStore(STORES.examinations, { keyPath: 'key' });
                    store.createIndex('attempt_id', 'attempt_id', { unique: false });
                    store.createIndex('student_id', 'student_id', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.answers)) {
                    const store = db.createObjectStore(STORES.answers, { keyPath: 'id' });
                    store.createIndex('attempt_id', 'attempt_id', { unique: false });
                    store.createIndex('question_id', 'question_id', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.syncQueue)) {
                    const store = db.createObjectStore(STORES.syncQueue, { keyPath: 'id' });
                    store.createIndex('sync_status', 'sync_status', { unique: false });
                    store.createIndex('exam_attempt_id', 'exam_attempt_id', { unique: false });
                    store.createIndex('created_at', 'created_at', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.timerState)) {
                    db.createObjectStore(STORES.timerState, { keyPath: 'attempt_id' });
                }
                if (!db.objectStoreNames.contains(STORES.meta)) {
                    db.createObjectStore(STORES.meta, { keyPath: 'key' });
                }
            }

            if (oldVersion < 2 && !db.objectStoreNames.contains(STORES.attemptSessions)) {
                db.createObjectStore(STORES.attemptSessions, { keyPath: 'attempt_id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

async function tx(storeName, mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);
        const result = fn(store, transaction);
        transaction.oncomplete = () => resolve(result);
        transaction.onerror = () => reject(transaction.error);
    });
}

function cloneForStorage(value) {
    return JSON.parse(JSON.stringify(value));
}

async function put(storeName, value) {
    return tx(storeName, 'readwrite', (store) => store.put(cloneForStorage(value)));
}

async function get(storeName, key) {
    return tx(storeName, 'readonly', (store) => new Promise((resolve, reject) => {
        const request = store.get(key);
        request.onsuccess = () => resolve(request.result ?? null);
        request.onerror = () => reject(request.error);
    }));
}

async function getAll(storeName, indexName = null, query = null) {
    return tx(storeName, 'readonly', (store) => new Promise((resolve, reject) => {
        const source = indexName ? store.index(indexName) : store;
        const request = query ? source.getAll(query) : source.getAll();
        request.onsuccess = () => resolve(request.result ?? []);
        request.onerror = () => reject(request.error);
    }));
}

async function remove(storeName, key) {
    return tx(storeName, 'readwrite', (store) => store.delete(key));
}

export const examOfflineDb = {
    STORES,

    open: openDb,
    put,
    get,
    getAll,
    remove,

    examKey(examinationId, studentId) {
        return `${examinationId}:${studentId}`;
    },

    answerKey(attemptId, questionId) {
        return `${attemptId}:${questionId}`;
    },

    async saveExamPackage(examinationId, studentId, packageData) {
        await put(STORES.examinations, {
            key: this.examKey(examinationId, studentId),
            examination_id: examinationId,
            student_id: studentId,
            attempt_id: packageData.attempt_id,
            package: packageData,
            prepared_at: packageData.prepared_at || new Date().toISOString(),
        });
    },

    async getExamPackage(examinationId, studentId) {
        const row = await get(STORES.examinations, this.examKey(examinationId, studentId));
        return row?.package ?? null;
    },

    async getExamPackageByAttempt(attemptId) {
        const rows = await getAll(STORES.examinations);
        return rows.find((row) => row.attempt_id === attemptId)?.package ?? null;
    },

    async getExamRow(examinationId, studentId) {
        return get(STORES.examinations, this.examKey(examinationId, studentId));
    },

    async saveAnswer(attemptId, questionId, answer, isFlagged = false, syncStatus = 'pending') {
        const id = this.answerKey(attemptId, questionId);
        const existing = await get(STORES.answers, id);
        const revision = Date.now();

        await put(STORES.answers, {
            id,
            attempt_id: attemptId,
            question_id: questionId,
            answer,
            is_flagged: isFlagged,
            client_revision: String(revision),
            sync_status: syncStatus,
            updated_at: new Date().toISOString(),
            created_at: existing?.created_at || new Date().toISOString(),
        });

        return revision;
    },

    async getAnswersForAttempt(attemptId) {
        return getAll(STORES.answers, 'attempt_id', attemptId);
    },

    async markAnswerSynced(attemptId, questionId) {
        const id = this.answerKey(attemptId, questionId);
        const existing = await get(STORES.answers, id);
        if (!existing) {
            return;
        }
        existing.sync_status = 'synced';
        await put(STORES.answers, existing);
    },

    async saveTimerState(attemptId, state) {
        await put(STORES.timerState, { attempt_id: attemptId, ...state });
    },

    async getTimerState(attemptId) {
        return get(STORES.timerState, attemptId);
    },

    async saveAttemptState(attemptId, state) {
        await put(STORES.attemptSessions, {
            attempt_id: attemptId,
            ...state,
            updated_at: new Date().toISOString(),
        });
    },

    async getAttemptState(attemptId) {
        return get(STORES.attemptSessions, attemptId);
    },

    async setMeta(key, value) {
        await put(STORES.meta, { key, value });
    },

    async getMeta(key) {
        const row = await get(STORES.meta, key);
        return row?.value ?? null;
    },

    async hasActiveExamination() {
        const rows = await getAll(STORES.examinations);
        return rows.some((row) => row.package?.active === true);
    },

    async hasPendingSync() {
        const queue = await getAll(STORES.syncQueue);
        return queue.some((e) => e.sync_status === 'pending' || e.sync_status === 'failed');
    },

    async clearExamData(examinationId, studentId, attemptId) {
        await remove(STORES.examinations, this.examKey(examinationId, studentId));
        const answers = await this.getAnswersForAttempt(attemptId);
        for (const answer of answers) {
            await remove(STORES.answers, answer.id);
        }
        await remove(STORES.timerState, attemptId);
        await remove(STORES.attemptSessions, attemptId);
    },

    async estimateStorageAvailable() {
        if (navigator.storage?.estimate) {
            const estimate = await navigator.storage.estimate();
            return {
                usage: estimate.usage || 0,
                quota: estimate.quota || 0,
                available: (estimate.quota || 0) - (estimate.usage || 0),
            };
        }
        return { usage: 0, quota: 0, available: 50 * 1024 * 1024 };
    },

    async requestPersistentStorage() {
        if (navigator.storage?.persist) {
            return navigator.storage.persist();
        }
        return false;
    },
};

export default examOfflineDb;
