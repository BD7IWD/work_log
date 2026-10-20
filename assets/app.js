/* ===================================================================
   工作留痕记录 - 前端交互（原生 JS，无依赖）
   依赖：window.WD = { root: '/wd/' }  由页面注入
   =================================================================== */
(function () {
    'use strict';

    var WD = window.WD || {};
    var ROOT = WD.root || '/';

    /* ---------------- 工具 ---------------- */
    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    function apiUrl(p) {
        return ROOT + String(p).replace(/^\/+/, '');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ---------------- Toast ---------------- */
    function toast(msg, type, ms) {
        var box = qs('#toastBox');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toastBox';
            document.body.appendChild(box);
        }
        var el = document.createElement('div');
        el.className = 'toast' + (type ? ' ' + type : '');
        el.textContent = msg;
        box.appendChild(el);
        var t = ms || (type === 'err' ? 3600 : 2200);
        setTimeout(function () {
            el.style.transition = 'opacity .2s, transform .2s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(6px)';
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
        }, t);
    }

    /* ---------------- XHR 请求（带上传进度） ---------------- */
    function request(url, formData, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiUrl(url), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (onProgress && xhr.upload) {
                xhr.upload.onprogress = function (ev) {
                    if (ev.lengthComputable) onProgress(ev.loaded, ev.total);
                };
            }
            xhr.onload = function () {
                var data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
                if (xhr.status === 401 && data && data.need_login) {
                    toast('登录已过期，正在跳转…', 'err');
                    setTimeout(function () { location.href = apiUrl('login.php'); }, 900);
                    return reject(data);
                }
                if (!data) {
                    return reject({ ok: 0, msg: '服务器返回异常（HTTP ' + xhr.status + '）' });
                }
                resolve(data);
            };
            xhr.onerror = function () { reject({ ok: 0, msg: '网络错误，请检查连接' }); };
            xhr.ontimeout = function () { reject({ ok: 0, msg: '请求超时' }); };
            xhr.send(formData);
        });
    }

    function getJson(url) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', apiUrl(url), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () {
                var data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
                if (!data) return reject({ ok: 0, msg: '服务器返回异常（HTTP ' + xhr.status + '）' });
                if (xhr.status === 401 && data.need_login) {
                    toast('登录已过期，正在跳转…', 'err');
                    setTimeout(function () { location.href = apiUrl('login.php'); }, 900);
                    return reject(data);
                }
                resolve(data);
            };
            xhr.onerror = function () { reject({ ok: 0, msg: '网络错误' }); };
            xhr.send();
        });
    }

    /* ---------------- 模态框 ---------------- */
    var modalStack = [];

    function openModal(el) {
        if (typeof el === 'string') el = qs(el);
        if (!el) return;
        el.classList.add('show');
        document.body.style.overflow = 'hidden';
        if (modalStack.indexOf(el) === -1) modalStack.push(el);
        var first = el.querySelector('input:not([type=hidden]), textarea, select');
        if (first && window.innerWidth >= 768) {
            setTimeout(function () { try { first.focus(); } catch (e) {} }, 120);
        }
    }

    function closeModal(el) {
        if (typeof el === 'string') el = qs(el);
        if (!el) return;
        el.classList.remove('show');
        var i = modalStack.indexOf(el);
        if (i > -1) modalStack.splice(i, 1);
        if (!modalStack.length) document.body.style.overflow = '';
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        // data-close 关闭
        var closer = t.closest ? t.closest('[data-close]') : null;
        if (closer) {
            var m = closer.closest('.modal');
            if (m) { ev.preventDefault(); closeModal(m); }
            return;
        }
        // 点遮罩关闭
        if (t.classList && t.classList.contains('modal')) {
            closeModal(t);
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modalStack.length) {
            closeModal(modalStack[modalStack.length - 1]);
        }
    });

    /* ---------------- 复制到剪贴板 ---------------- */
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            ta.style.top = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject();
            } catch (e) {
                document.body.removeChild(ta);
                reject(e);
            }
        });
    }

    /* ---------------- 删除记录 ---------------- */
    function deleteRecord(id, cb) {
        var fd = new FormData();
        fd.append('id', id);
        request('api/record_delete.php', fd).then(function (res) {
            if (res.ok) {
                toast('已删除', 'ok');
                cb && cb(res);
            } else {
                toast(res.msg || '删除失败', 'err');
            }
        }).catch(function (e) { toast((e && e.msg) || '删除失败', 'err'); });
    }

    /* ---------------- 分享开关 ---------------- */
    function toggleShare(id, action, cb) {
        var fd = new FormData();
        fd.append('id', id);
        fd.append('action', action);
        request('api/share_toggle.php', fd).then(function (res) {
            if (res.ok) {
                cb && cb(res);
            } else {
                toast(res.msg || '操作失败', 'err');
            }
        }).catch(function (e) { toast((e && e.msg) || '操作失败', 'err'); });
    }

    /* ---------------- 格式 ---------------- */
    function fmtSize(b) {
        b = Number(b) || 0;
        if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024) return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }

    /* ---------------- 多选标签下拉（涉及客户，可复用） ---------------- */
    function createTagSelect(cfg) {
        var wrap     = cfg.wrap;
        var search   = cfg.search;
        var tagsBox  = cfg.tags;
        var menu     = cfg.menu;
        var hidden   = cfg.hidden;
        var max      = cfg.max || 12;
        var options  = (cfg.options || []).slice();
        var selected = [];
        var filtered = [];
        var activeIdx = -1;

        function close() {
            wrap.classList.remove('open', 'up');
            activeIdx = -1;
        }

        function open() {
            wrap.classList.add('open');
            var box = wrap.querySelector('.tagsel-box');
            var bodyEl = wrap.closest('.modal-body') || document.body;
            if (box && bodyEl) {
                var br = box.getBoundingClientRect();
                var pr = bodyEl.getBoundingClientRect();
                var below = pr.bottom - br.bottom;
                var above = br.top - pr.top;
                if (below < 240 && above > below) {
                    wrap.classList.add('up');
                } else {
                    wrap.classList.remove('up');
                }
            }
            renderMenu();
        }

        function renderTags() {
            tagsBox.innerHTML = '';
            selected.forEach(function (name, i) {
                var t = document.createElement('span');
                t.className = 'ts-tag';
                var nm = document.createElement('span');
                nm.className = 'ts-name';
                nm.textContent = name;
                var x = document.createElement('button');
                x.type = 'button';
                x.className = 'ts-x';
                x.setAttribute('aria-label', '移除 ' + name);
                x.textContent = '×';
                x.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    selected.splice(i, 1);
                    render();
                });
                t.appendChild(nm);
                t.appendChild(x);
                tagsBox.appendChild(t);
            });
            if (hidden) hidden.value = selected.join(',');
        }

        function renderMenu() {
            if (!menu) return;
            var raw = search.value.trim();
            var kw = raw.toLowerCase();

            filtered = options.filter(function (n) {
                return selected.indexOf(n) === -1
                    && (kw === '' || n.toLowerCase().indexOf(kw) > -1);
            });

            menu.innerHTML = '';

            filtered.forEach(function (name, i) {
                var d = document.createElement('div');
                d.className = 'ts-opt' + (i === activeIdx ? ' active' : '');
                d.textContent = name;
                d.addEventListener('pointerdown', function (ev) {
                    ev.preventDefault();
                    add(name);
                });
                menu.appendChild(d);
            });

            // 允许手填新客户
            if (raw !== '' && selected.indexOf(raw) === -1 && options.indexOf(raw) === -1) {
                var a = document.createElement('div');
                a.className = 'ts-opt ts-add';
                a.textContent = '＋ 添加「' + raw + '」';
                a.addEventListener('pointerdown', function (ev) {
                    ev.preventDefault();
                    add(raw);
                });
                menu.appendChild(a);
            }

            if (!menu.children.length) {
                var e = document.createElement('div');
                e.className = 'ts-empty';
                e.textContent = kw === ''
                    ? '暂无选项，直接输入客户名后回车添加'
                    : '没有匹配项，回车可直接添加';
                menu.appendChild(e);
            }
        }

        function render() {
            renderTags();
            renderMenu();
        }

        function add(name) {
            name = String(name === undefined || name === null ? '' : name)
                .replace(/[,，、;；|/]/g, '').trim();
            if (name === '') return;
            if (selected.indexOf(name) > -1) {
                search.value = '';
                render();
                return;
            }
            if (selected.length >= max) {
                toast('最多选 ' + max + ' 个客户', 'err');
                return;
            }
            if (options.indexOf(name) === -1) options.push(name);
            selected.push(name);
            search.value = '';
            activeIdx = -1;
            render();
            open();
        }

        function setValue(str) {
            var arr = String(str === undefined || str === null ? '' : str)
                .split(/[,，、;；|/]/)
                .map(function (s) { return s.trim(); })
                .filter(function (s) { return s !== ''; });
            var seen = {};
            selected = arr.filter(function (s) {
                if (seen[s]) return false;
                seen[s] = 1;
                return true;
            });
            selected.forEach(function (s) {
                if (options.indexOf(s) === -1) options.push(s);
            });
            if (search) search.value = '';
            render();
        }

        function getValue() { return selected.join(','); }

        if (search) {
            search.addEventListener('focus', open);
            search.addEventListener('input', function () {
                activeIdx = -1;
                open();
            });
            search.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    if (activeIdx > -1 && filtered[activeIdx]) {
                        add(filtered[activeIdx]);
                    } else if (search.value.trim() !== '') {
                        add(search.value.trim());
                    }
                } else if (ev.key === 'Backspace' && search.value === '' && selected.length) {
                    selected.pop();
                    render();
                } else if (ev.key === 'ArrowDown') {
                    ev.preventDefault();
                    activeIdx = Math.min(filtered.length - 1, activeIdx + 1);
                    renderMenu();
                } else if (ev.key === 'ArrowUp') {
                    ev.preventDefault();
                    activeIdx = Math.max(-1, activeIdx - 1);
                    renderMenu();
                } else if (ev.key === 'Escape') {
                    close();
                    search.blur();
                }
            });
        }

        var box = wrap.querySelector('.tagsel-box');
        if (box) {
            box.addEventListener('click', function (ev) {
                if (ev.target === box || (ev.target.classList && ev.target.classList.contains('tagsel-tags'))) {
                    if (search) search.focus();
                }
            });
        }

        document.addEventListener('pointerdown', function (ev) {
            if (!wrap.contains(ev.target)) close();
        });

        return {
            setValue: setValue,
            getValue: getValue,
            add: add,
            clear: function () { selected = []; render(); }
        };
    }

    /* ---------------- 导出 ---------------- */
    window.WD = {
        root: ROOT,
        apiUrl: apiUrl,
        toast: toast,
        request: request,
        getJson: getJson,
        openModal: openModal,
        closeModal: closeModal,
        deleteRecord: deleteRecord,
        toggleShare: toggleShare,
        copyText: copyText,
        createTagSelect: createTagSelect,
        escapeHtml: escapeHtml,
        fmtSize: fmtSize,
        $: qs,
        $$: qsa
    };
})();
