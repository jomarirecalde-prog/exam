import examOfflineDb from './db';

const DEVICE_KEY = 'exam-pwa-device-id';

export function getDeviceIdentifier() {
    let id = localStorage.getItem(DEVICE_KEY);
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(DEVICE_KEY, id);
    }
    return id;
}

export function getDeviceName() {
    const ua = navigator.userAgent;
    if (/iPhone|iPad|iPod/.test(ua)) {
        return 'iOS Device';
    }
    if (/Android/.test(ua)) {
        return 'Android Device';
    }
    if (/Windows/.test(ua)) {
        return 'Windows Device';
    }
    if (/Mac/.test(ua)) {
        return 'Mac Device';
    }
    return 'Web Browser';
}

export async function bindDeviceToStudent(studentId) {
    await examOfflineDb.setMeta('bound_student_id', studentId);
}

export async function assertStudentBinding(studentId) {
    const bound = await examOfflineDb.getMeta('bound_student_id');
    if (bound && bound !== studentId) {
        throw new Error('Local examination data belongs to another student account on this device.');
    }
    if (!bound) {
        await bindDeviceToStudent(studentId);
    }
}

export default { getDeviceIdentifier, getDeviceName, bindDeviceToStudent, assertStudentBinding };
