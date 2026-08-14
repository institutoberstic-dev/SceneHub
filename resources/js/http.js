import axios from 'axios';

export const api = axios.create({
    baseURL: '/',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

const setToken = (token) => {
    if (!token) return;

    api.defaults.headers.common['X-CSRF-TOKEN'] = token;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', token);
};

setToken(document.querySelector('meta[name="csrf-token"]')?.content);

export async function tokenReloader() {
    const { data } = await api.get('/csrf/refresh');
    setToken(data.token);
    return data.token;
}

export async function csrfRequest(config) {
    await tokenReloader();
    return api.request(config);
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const request = error.config;

        if (error.response?.status === 419 && request && !request.__csrfRetried) {
            request.__csrfRetried = true;
            const token = await tokenReloader();
            request.headers['X-CSRF-TOKEN'] = token;
            return api.request(request);
        }

        return Promise.reject(error);
    },
);

export function errorMessage(error, fallback = 'No fue posible completar la solicitud.') {
    const errors = error.response?.data?.errors;
    if (errors) return Object.values(errors).flat()[0];
    return error.response?.data?.message || fallback;
}
