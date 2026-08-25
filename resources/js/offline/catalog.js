import examOfflineDb from './db';

export const EXAM_STATUS = {
    READY: 'ready_for_offline',
    NOT_PREPARED: 'not_prepared',
    IN_PROGRESS: 'in_progress',
    SUBMISSION_PENDING: 'submission_pending',
    COMPLETED: 'completed',
    LOCKED: 'locked',
    INTERNET_REQUIRED: 'internet_required',
};

export function deriveExamStatus(entry) {
    const pkg = entry?.package;
    const local = entry?.local_state;

    if (local?.phase === 'locked' || local?.status === 'LOCKED_VIOLATION_LIMIT') {
        return EXAM_STATUS.LOCKED;
    }
    if (local?.phase === 'pending_submission' || pkg?.attempt_state?.pending_submission_at) {
        return EXAM_STATUS.SUBMISSION_PENDING;
    }
    if (local?.phase === 'active' || pkg?.attempt_state?.status === 'IN_PROGRESS') {
        return EXAM_STATUS.IN_PROGRESS;
    }
    if (pkg?.offline_ready && pkg?.integrity_verified) {
        return EXAM_STATUS.READY;
    }
    if (pkg) {
        return EXAM_STATUS.INTERNET_REQUIRED;
    }
    return EXAM_STATUS.NOT_PREPARED;
}

export function statusLabel(status) {
    const labels = {
        [EXAM_STATUS.READY]: 'Ready for Offline Use',
        [EXAM_STATUS.NOT_PREPARED]: 'Not Prepared',
        [EXAM_STATUS.IN_PROGRESS]: 'Continue Examination',
        [EXAM_STATUS.SUBMISSION_PENDING]: 'Waiting for Synchronization',
        [EXAM_STATUS.COMPLETED]: 'Completed',
        [EXAM_STATUS.LOCKED]: 'Examination Locked',
        [EXAM_STATUS.INTERNET_REQUIRED]: 'Internet Required',
    };
    return labels[status] || status;
}

export async function listCatalogEntries(studentId) {
    const rows = await examOfflineDb.getAll(examOfflineDb.STORES.examinations);
    const filtered = rows.filter((row) => row.student_id === studentId);

    const entries = [];
    for (const row of filtered) {
        const attemptId = row.attempt_id || row.package?.attempt_id;
        const localState = attemptId ? await examOfflineDb.getAttemptState(attemptId) : null;
        const status = deriveExamStatus({ package: row.package, local_state: localState });
        const answers = attemptId
            ? await examOfflineDb.getAnswersForAttempt(attemptId)
            : [];

        entries.push({
            examination_id: row.examination_id,
            attempt_id: attemptId,
            title: row.package?.title || 'Examination',
            subject_code: row.package?.subject_code || '',
            subject_name: row.package?.subject_name || '',
            duration_minutes: row.package?.duration_minutes || 0,
            status,
            status_label: statusLabel(status),
            prepared_at: row.prepared_at,
            offline_ready: Boolean(row.package?.offline_ready),
            local_state: localState,
            answered_count: answers.filter((a) => a.answer).length,
            question_count: row.package?.questions?.length || 0,
            take_url: row.package?.take_url || `/offline/examinations/${row.examination_id}/take`,
        });
    }

    return entries.sort((a, b) => (b.prepared_at || '').localeCompare(a.prepared_at || ''));
}

export async function upsertCatalogEntry(examinationId, studentId, packageData) {
    await examOfflineDb.saveExamPackage(examinationId, studentId, packageData);
}

export default { EXAM_STATUS, deriveExamStatus, statusLabel, listCatalogEntries, upsertCatalogEntry };
