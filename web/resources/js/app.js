import './bootstrap';
import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;
window.Alpine = Alpine;

const humanSize = (bytes) => {
    if (bytes >= 1024 ** 3) return (bytes / 1024 ** 3).toFixed(1) + ' GB';
    if (bytes >= 1024 ** 2) return (bytes / 1024 ** 2).toFixed(1) + ' MB';
    return Math.max(1, Math.round(bytes / 1024)) + ' KB';
};

/**
 * نموذج رفع الملف: يفحص النوع والحجم في المتصفح (للراحة فقط؛ الخادم يعيد الفحص دائمًا)
 * ثم يرسل الملف عبر XHR ليُظهر شريط تقدم الرفع.
 */
Alpine.data('uploader', (cfg) => ({
    type: cfg.initialType,
    file: null,
    dragging: false,
    uploading: false,
    percent: 0,
    error: null,

    get extensions() {
        return cfg.types[this.type] || [];
    },
    get accept() {
        return this.extensions.map((e) => '.' + e).join(',');
    },
    get fileSize() {
        return this.file ? humanSize(this.file.size) : '';
    },

    changeType() {
        this.clearFile();
    },
    clearFile() {
        this.file = null;
        this.error = null;
        if (this.$refs.file) this.$refs.file.value = '';
    },
    pick(event) {
        this.setFile(event.target.files[0]);
    },
    /** صورة ملتقطة بالكاميرا: تُنقل إلى حقل الرفع الأصلي (name="file") ليُرسل بالطريقة نفسها. */
    capture(event) {
        const files = event.target.files;
        if (!files.length) return;
        this.$refs.file.files = files;
        this.setFile(files[0]);
    },
    drop(event) {
        this.dragging = false;
        const files = event.dataTransfer.files;
        if (files.length) {
            this.$refs.file.files = files;
            this.setFile(files[0]);
        }
    },
    setFile(file) {
        this.error = null;
        if (!file) {
            this.file = null;
            return;
        }
        const ext = file.name.split('.').pop().toLowerCase();
        if (!this.extensions.includes(ext)) {
            this.error = cfg.messages.extension.replace(':types', this.extensions.join(', ').toUpperCase());
            this.clearFileKeepError();
            return;
        }
        if (file.size > cfg.maxBytes) {
            this.error = cfg.messages.tooLarge.replace(':max', cfg.maxLabel);
            this.clearFileKeepError();
            return;
        }
        this.file = file;
    },
    clearFileKeepError() {
        this.file = null;
        if (this.$refs.file) this.$refs.file.value = '';
    },

    submit(event) {
        if (!window.XMLHttpRequest || !window.FormData || this.uploading) return;
        if (!this.file) {
            event.preventDefault();
            this.error = cfg.messages.required;
            return;
        }
        event.preventDefault();

        const form = event.target;
        const xhr = new XMLHttpRequest();
        this.uploading = true;
        this.percent = 0;
        this.error = null;

        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) this.percent = Math.round((e.loaded / e.total) * 100);
        };
        xhr.onerror = () => this.fail(cfg.messages.network);
        xhr.onload = () => {
            let body = {};
            try {
                body = JSON.parse(xhr.responseText);
            } catch (e) {}

            if (xhr.status >= 200 && xhr.status < 300 && body.redirect) {
                window.location.href = body.redirect;
            } else if (xhr.status === 422 && body.errors) {
                this.fail(Object.values(body.errors).flat()[0]);
            } else if (xhr.status === 413) {
                this.fail(cfg.messages.tooLarge.replace(':max', cfg.maxLabel));
            } else if (xhr.status === 429) {
                this.fail(cfg.messages.throttle);
            } else {
                this.fail(cfg.messages.generic);
            }
        };
        xhr.send(new FormData(form));
    },
    fail(message) {
        this.uploading = false;
        this.error = message;
    },
}));

/** يستطلع حالة التحليل كل ثانيتين ويحدّث الشريط، ثم ينتقل إلى النتيجة عند الانتهاء. */
Alpine.data('progressTracker', (cfg) => ({
    progress: cfg.progress,
    statusLabel: cfg.statusLabel,
    stageLabel: cfg.stageLabel,
    stage: cfg.stage,
    hint: null,
    lostConnection: false,

    init() {
        this.poll();
    },
    get steps() {
        return cfg.steps;
    },
    stepState(key) {
        const order = cfg.steps.map((s) => s.key);
        const current = order.indexOf(this.stage);
        const idx = order.indexOf(key);
        if (current === -1) return 'todo';
        return idx < current ? 'done' : idx === current ? 'active' : 'todo';
    },
    async poll() {
        try {
            const response = await fetch(cfg.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.ok) {
                const data = await response.json();
                this.lostConnection = false;
                this.progress = data.progress;
                this.statusLabel = data.status_label;
                this.stageLabel = data.stage_label;
                this.stage = data.stage;
                this.hint = data.hint_text || null;
                if (data.final) {
                    window.location.href = data.url;
                    return;
                }
            } else if (response.status === 401 || response.status === 419) {
                window.location.reload();
                return;
            }
        } catch (e) {
            this.lostConnection = true;
        }
        setTimeout(() => this.poll(), 2000);
    },
}));

/** نسخ نص إلى الحافظة مع تأكيد مرئي. */
Alpine.data('copyText', (text, doneLabel) => ({
    done: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(text);
        } catch (e) {
            const helper = document.createElement('textarea');
            helper.value = text;
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            helper.remove();
        }
        this.done = true;
        setTimeout(() => (this.done = false), 2000);
    },
}));

/** زر إظهار/إخفاء كلمة المرور في حقول x-form.input reveal. */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-reveal]');
    if (!button) return;
    const input = document.getElementById(button.dataset.reveal);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const label = show ? button.dataset.labelHide : button.dataset.labelShow;
    button.setAttribute('aria-pressed', String(show));
    button.setAttribute('aria-label', label);
    button.title = label;
    button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});

/**
 * حالات الحقول في نماذج الدخول والتسجيل: عند تعديل حقل عليه خطأ من الخادم يزول تمييز الخطأ،
 * وعند مغادرة حقل صالح يظهر تمييز النجاح. رسائل الخطأ نفسها تبقى من الخادم (مترجمة).
 */
document.querySelectorAll('form[data-field-states]').forEach((form) => {
    form.addEventListener('input', (event) => {
        const field = event.target;
        if (!field.classList?.contains('form-control')) return;
        if (field.classList.contains('is-invalid')) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            document.getElementById(field.id + '-error')?.remove();
        }
        field.classList.remove('is-valid');
    });
    form.addEventListener('focusout', (event) => {
        const field = event.target;
        if (!field.classList?.contains('form-control') || field.type === 'password') return;
        field.classList.toggle('is-valid', field.value.trim() !== '' && field.checkValidity() && !field.classList.contains('is-invalid'));
    });
});

Alpine.start();
