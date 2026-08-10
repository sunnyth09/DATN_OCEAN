export const trimTrailingSlash = (value = '') => String(value).replace(/\/+$/, '');

export const getApiBaseUrl = () => {
  const configuredUrl = import.meta.env.VITE_API_URL;

  if (configuredUrl) {
    return trimTrailingSlash(configuredUrl);
  }

  return `${window.location.protocol}//${window.location.hostname}:8383/api`;
};

export const getAppBaseUrl = () => {
  const configuredUrl = import.meta.env.VITE_BASE_URL || import.meta.env.VITE_APP_URL;

  if (configuredUrl && /^https?:\/\//i.test(configuredUrl)) {
    return trimTrailingSlash(configuredUrl.replace(/\/api$/, ''));
  }

  return getApiBaseUrl().replace(/\/api$/, '');
};

export const getStorageUrl = (path = '') => {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;

  const normalizedPath = String(path)
    .replace(/^\/+/, '')
    .replace(/^storage\/+/, '');

  return `${getAppBaseUrl()}/storage/${normalizedPath}`;
};

export const getAbsoluteUrl = (path = '') => {
  if (!path) return '';
  if (/^(https?:|data:|blob:)/i.test(path)) return path;

  const normalizedPath = String(path).startsWith('/') ? path : `/${path}`;
  return `${getAppBaseUrl()}${normalizedPath}`;
};
