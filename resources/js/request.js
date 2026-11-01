export async function request(url, { method = 'GET', body, responseType = 'json', authenticated = true } = {}) {
    const headers = new Headers({ Accept: responseType === 'blob' ? '*/*' : 'application/json' });
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
        const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
        if (!cookie) throw Object.assign(new Error('Your session has expired. Refresh this page to sign in again.'), { status: 419 });
        headers.set('X-XSRF-TOKEN', decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)));
    }
    if (body !== undefined && !(body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
    }

    const response = await fetch(url, { method, body, headers, credentials: 'same-origin' });
    if (authenticated && response.status === 401) {
        window.location.replace('/');
        throw Object.assign(new Error('Your session has expired. Please sign in again.'), { status: 401 });
    }
    const data = responseType === 'blob' && response.ok
        ? await response.blob()
        : await response.json().catch(() => null);
    return { response, data };
}