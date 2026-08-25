export function verifyExamPackageIntegrity(pkg) {
    const errors = [];

    if (!pkg?.examination_id) errors.push('Missing examination ID');
    if (!pkg?.attempt_id) errors.push('Missing attempt authorization');
    if (!pkg?.student_id) errors.push('Missing student binding');
    if (!pkg?.title) errors.push('Missing examination title');
    if (!Array.isArray(pkg?.questions) || pkg.questions.length === 0) errors.push('Missing questions');
    if (!pkg?.duration_minutes) errors.push('Missing duration');
    if (!pkg?.policy_version) errors.push('Missing policy version');
    if (!pkg?.max_warnings) errors.push('Missing warning configuration');

    for (const [index, question] of (pkg?.questions || []).entries()) {
        if (!question.id) errors.push(`Question ${index + 1} missing ID`);
        if (!question.text) errors.push(`Question ${index + 1} missing text`);
        if (question.type === 'multiple_choice' && (!question.choices || question.choices.length === 0)) {
            errors.push(`Question ${index + 1} missing choices`);
        }
    }

    return {
        valid: errors.length === 0,
        errors,
        checks: {
            details: Boolean(pkg?.title),
            questions: Array.isArray(pkg?.questions) && pkg.questions.length > 0,
            choices: (pkg?.questions || []).every((q) => q.choices?.length > 0 || q.type !== 'multiple_choice'),
            media: true,
            instructions: pkg?.instructions !== undefined,
            policy: Boolean(pkg?.policy_version),
            timer: Boolean(pkg?.duration_minutes),
            authorization: Boolean(pkg?.attempt_id && pkg?.offline_session_id),
        },
    };
}

export default { verifyExamPackageIntegrity };
