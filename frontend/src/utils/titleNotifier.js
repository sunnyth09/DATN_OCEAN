/**
 * Tab Title Notification Manager (Facebook style tab title flashing synchronized with bell badge)
 */

let originalTitle = document.title || 'Ocean Sport';
let flashInterval = null;
let autoStopTimeout = null;
let isFlashing = false;
let currentNoticeText = '';
let eventListenersAttached = false;

/**
 * Updates the original base page title (called on route changes)
 * @param {string} title - The title for the current route/page
 */
export const setOriginalTitle = (title) => {
  if (title) {
    originalTitle = title;
  }
  if (!isFlashing) {
    document.title = originalTitle;
  }
};

export const getOriginalTitle = () => originalTitle;

/**
 * Stop title flashing and restore the original title
 */
export const stopFlashTitle = () => {
  if (flashInterval) {
    clearInterval(flashInterval);
    flashInterval = null;
  }
  if (autoStopTimeout) {
    clearTimeout(autoStopTimeout);
    autoStopTimeout = null;
  }
  if (isFlashing) {
    isFlashing = false;
    document.title = originalTitle;
  }
  removeInteractionListeners();
};

const handleUserInteraction = () => {
  if (isFlashing) {
    stopFlashTitle();
  }
};

const handleVisibilityChange = () => {
  if (!document.hidden && isFlashing) {
    stopFlashTitle();
  }
};

const addInteractionListeners = () => {
  if (eventListenersAttached) return;
  window.addEventListener('focus', handleUserInteraction);
  window.addEventListener('click', handleUserInteraction);
  window.addEventListener('keydown', handleUserInteraction);
  document.addEventListener('visibilitychange', handleVisibilityChange);
  eventListenersAttached = true;
};

const removeInteractionListeners = () => {
  if (!eventListenersAttached) return;
  window.removeEventListener('focus', handleUserInteraction);
  window.removeEventListener('click', handleUserInteraction);
  window.removeEventListener('keydown', handleUserInteraction);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  eventListenersAttached = false;
};

/**
 * Flash browser tab title in Facebook style
 * @param {string} noticeText - Message to display (e.g. "(5) Bạn có 5 thông báo mới!")
 * @param {Object} options
 * @param {number} [options.interval=1000] - interval between title toggles in ms
 * @param {number} [options.timeout=30000] - auto stop timeout in ms
 */
export const flashTitle = (noticeText = '(1) Bạn có 1 thông báo mới!', options = {}) => {
  const { interval = 1000, timeout = 30000 } = options;

  currentNoticeText = noticeText;

  // Preserve current document.title if originalTitle wasn't stored yet
  if (!originalTitle || originalTitle === currentNoticeText) {
    originalTitle = document.title || 'Ocean Sport';
  }

  // Stop any active flashing first
  stopFlashTitle();

  isFlashing = true;
  let showNotice = true;
  document.title = currentNoticeText;

  flashInterval = setInterval(() => {
    showNotice = !showNotice;
    document.title = showNotice ? currentNoticeText : originalTitle;
  }, interval);

  addInteractionListeners();

  if (timeout > 0) {
    autoStopTimeout = setTimeout(() => {
      stopFlashTitle();
    }, timeout);
  }
};

/**
 * Initialize global event listeners for title notifications synchronized with notification counts
 */
export const initTitleNotifier = () => {
  window.addEventListener('play-notif-sound', (e) => {
    const count = e?.detail?.count;
    if (count === 0) {
      stopFlashTitle();
      return;
    }
    const customMessage = e?.detail?.message;
    const defaultMsg = count ? `(${count}) Bạn có ${count} thông báo mới!` : '(1) Bạn có 1 thông báo mới!';
    const notice = customMessage || defaultMsg;
    flashTitle(notice);
  });

  window.addEventListener('new-title-notification', (e) => {
    const count = e?.detail?.count;
    if (count === 0) {
      stopFlashTitle();
      return;
    }
    const customMessage = e?.detail?.message;
    const defaultMsg = count ? `(${count}) Bạn có ${count} thông báo mới!` : '(1) Bạn có 1 thông báo mới!';
    const notice = customMessage || defaultMsg;
    flashTitle(notice);
  });

  window.addEventListener('has-new-unread-notifications', (e) => {
    const count = e?.detail?.count;
    if (!count || count === 0) {
      stopFlashTitle();
      return;
    }
    const notice = `(${count}) Bạn có ${count} thông báo mới!`;
    flashTitle(notice);
  });

  window.addEventListener('stop-title-notification', () => {
    stopFlashTitle();
  });
};
