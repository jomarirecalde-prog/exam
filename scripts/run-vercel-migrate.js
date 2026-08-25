const baseUrl = process.env.APP_URL
    || (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : '')
    || 'https://exam-tau-black.vercel.app';
const token = process.env.DEPLOY_SECRET;

if (!token || token === '[SENSITIVE]') {
    console.error('Missing DEPLOY_SECRET. Set it in Vercel env or pass it when running this script.');
    process.exit(1);
}

if (!baseUrl || baseUrl === '[SENSITIVE]') {
    console.error('Missing APP_URL. Set it in Vercel env or pass VERCEL_URL.');
    process.exit(1);
}
const url = `${baseUrl.replace(/\/$/, '')}/api/migrate.php?token=${encodeURIComponent(token)}`;

fetch(url)
    .then(async (response) => {
        const text = await response.text();
        console.log(text.slice(0, 4000));
        if (!response.ok) {
            process.exit(1);
        }
    })
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
