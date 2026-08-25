import examOfflineDb from './db';
import { verifyExamPackageIntegrity } from './integrity';
import { upsertCatalogEntry } from './catalog';
import { getDeviceIdentifier, getDeviceName, assertStudentBinding } from './device';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const PREP_STEPS = [
    { key: 'details', label: 'Examination details' },
    { key: 'questions', label: 'Questions' },
    { key: 'choices', label: 'Answer choices' },
    { key: 'media', label: 'Required media' },
    { key: 'instructions', label: 'Instructions' },
    { key: 'policy', label: 'Examination policy' },
    { key: 'timer', label: 'Timer settings' },
    { key: 'authorization', label: 'Authorization' },
];

function cacheExamShell(takeUrl) {
    if (navigator.serviceWorker?.controller && takeUrl) {
        navigator.serviceWorker.controller.postMessage({
            type: 'CACHE_SHELL_URLS',
            urls: [takeUrl],
        });
    }
}

export async function prepareExaminationOffline({ prepareUrl, examinationId, studentId, takeUrl, onProgress }) {
    await assertStudentBinding(studentId);

    const storage = await examOfflineDb.estimateStorageAvailable();
    if (storage.available < 2 * 1024 * 1024) {
        throw new Error('Your device does not have enough available storage. Please free up storage and try again.');
    }

    await examOfflineDb.requestPersistentStorage();

    for (let i = 0; i < PREP_STEPS.length - 1; i++) {
        onProgress?.({
            step: PREP_STEPS[i],
            index: i,
            total: PREP_STEPS.length,
            percent: Math.round(((i + 1) / PREP_STEPS.length) * 90),
        });
        await new Promise((r) => setTimeout(r, 100));
    }

    const response = await fetch(prepareUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            device_identifier: getDeviceIdentifier(),
            device_name: getDeviceName(),
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message || 'Unable to prepare examination for offline use.');
    }

    const resolvedTakeUrl = takeUrl || data.package?.take_url || `/offline/examinations/${examinationId}/take`;

    const packageData = {
        ...data.package,
        offline_session_id: data.offline_session_id,
        offline: data.offline,
        attempt_state: data.attempt,
        take_url: resolvedTakeUrl,
        active: false,
    };

    const integrity = verifyExamPackageIntegrity(packageData);
    onProgress?.({
        step: { key: 'integrity', label: 'Integrity check' },
        index: PREP_STEPS.length - 1,
        total: PREP_STEPS.length,
        percent: 95,
        integrity,
    });

    if (!integrity.valid) {
        throw new Error(`Examination preparation failed integrity check: ${integrity.errors.join(', ')}`);
    }

    packageData.integrity_verified = true;
    packageData.offline_ready = true;
    packageData.authorization_expires_at = data.authorization_expires_at || data.offline?.authorization_expires_at;

    await upsertCatalogEntry(examinationId, studentId, packageData);
    cacheExamShell(resolvedTakeUrl);

    onProgress?.({
        step: { key: 'ready', label: 'Exam ready for offline use' },
        index: PREP_STEPS.length,
        total: PREP_STEPS.length,
        percent: 100,
        complete: true,
        integrity,
    });

    return { ...data, package: packageData, integrity };
}

export async function loadPreparedExam(examinationId, studentId) {
    return examOfflineDb.getExamPackage(examinationId, studentId);
}

export async function loadPreparedExamByAttempt(attemptId) {
    return examOfflineDb.getExamPackageByAttempt(attemptId);
}

export function isAuthorizationValid(pkg) {
    if (!pkg?.offline_ready || !pkg?.integrity_verified) {
        return false;
    }
    if (pkg.authorization_expires_at) {
        return new Date(pkg.authorization_expires_at).getTime() > Date.now();
    }
    return true;
}

export { PREP_STEPS };

export default {
    prepareExaminationOffline,
    loadPreparedExam,
    loadPreparedExamByAttempt,
    isAuthorizationValid,
    PREP_STEPS,
};
