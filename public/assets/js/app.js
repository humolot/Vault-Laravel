/* GBX Vault — interface (jQuery) */
(function ($) {
    'use strict';

    var CFG = {};
    try { CFG = JSON.parse(document.getElementById('gbx-config').textContent); } catch (e) { CFG = { i18n: {}, urls: {} }; }
    var T = CFG.i18n || {};

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' } });
    $(document).ajaxError(function (e, xhr) {
        if (xhr.status === 401 || xhr.status === 419) {
            var r = (xhr.responseJSON && xhr.responseJSON.redirect) || CFG.urls.login;
            window.location.href = r;
        }
    });

    /* ---------------- utilidades ---------------- */

    function toast(message, type) {
        var icon = type === 'error' ? 'bi-exclamation-circle text-danger' : 'bi-check-circle text-success';
        var $t = $('<div class="toast align-items-center" role="status" aria-live="polite"><div class="d-flex"><div class="toast-body d-flex gap-2 align-items-center"><i class="bi ' + icon + '"></i><span></span></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
        $t.find('span').text(message);
        if (!$('#toasts').length) { $('body').append('<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>'); }
        $('#toasts').append($t);
        var tt = new bootstrap.Toast($t[0], { delay: 2600 });
        $t.on('hidden.bs.toast', function () { $t.remove(); });
        tt.show();
    }

    var clipboardTimer = null;
    function copyText(text, $btn) {
        var done = function () {
            toast(T.copied || 'Copied');
            if ($btn) {
                var $i = $btn.find('i'); var old = $i.attr('class');
                $btn.addClass('copied'); $i.attr('class', 'bi bi-check2');
                setTimeout(function () { $btn.removeClass('copied'); $i.attr('class', old); }, 1400);
            }
            if (CFG.clipboardSeconds > 0) {
                clearTimeout(clipboardTimer);
                clipboardTimer = setTimeout(function () {
                    if (navigator.clipboard && document.hasFocus()) {
                        navigator.clipboard.writeText('').then(function () { toast(T.clipboard_cleared || 'Clipboard cleared'); }).catch(function () {});
                    }
                }, CFG.clipboardSeconds * 1000);
            }
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text) ? done() : toast(T.copy_failed, 'error'); });
        } else {
            fallbackCopy(text) ? done() : toast(T.copy_failed, 'error');
        }
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        return ok;
    }

    function fetchSecret($el, action) {
        return $.post($el.data('reveal') || $el.data('copy-secret'), {
            field: $el.data('field'),
            index: $el.data('index') || 0,
            action: action
        });
    }

    /* ---------------- tema ---------------- */

    function applyTheme(pref, persist) {
        var eff = pref;
        if (!pref || pref === 'auto') {
            eff = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        }
        document.documentElement.setAttribute('data-bs-theme', eff);
        if (persist) {
            try { localStorage.setItem('gbx-theme', pref); } catch (e) {}
            if (CFG.authenticated && CFG.urls.theme) { $.post(CFG.urls.theme, { theme: pref }); }
        }
    }
    $(document).on('click', '[data-theme-toggle]', function () {
        var cur = document.documentElement.getAttribute('data-bs-theme');
        applyTheme(cur === 'dark' ? 'light' : 'dark', true);
        $('[data-theme-select]').val(cur === 'dark' ? 'light' : 'dark');
    });
    $(document).on('change', '[data-theme-select]', function () { applyTheme($(this).val(), true); });

    /* ---------------- copiar / revelar ---------------- */

    $(document).on('click', '[data-copy-text]', function (e) {
        e.preventDefault();
        copyText(String($(this).attr('data-copy-text')), $(this));
    });
    $(document).on('click', '[data-copy-input]', function (e) {
        e.preventDefault();
        copyText(String($($(this).data('copy-input')).val()).replace(/\s+/g, ''), $(this));
    });
    $(document).on('click', '[data-copy-secret]', function (e) {
        e.preventDefault();
        var $b = $(this);
        fetchSecret($b, 'copy').done(function (r) { copyText(r.value, $b); }).fail(function (x) { if (x.status !== 401) toast(T.error, 'error'); });
    });

    $(document).on('click', '[data-reveal]', function (e) {
        e.preventDefault();
        var $b = $(this);
        var $wrap = $b.closest('[data-secret-wrap]');
        var $v = $wrap.find('[data-secret-value]').first();
        var $icon = $b.find('i');
        if ($v.hasClass('revealed')) {
            $v.text($v.data('mask')).removeClass('revealed');
            $icon.removeClass('bi-eye-slash').addClass('bi-eye');
            clearTimeout($v.data('timer'));
            return;
        }
        fetchSecret($b, 'reveal').done(function (r) {
            if (!$v.data('mask')) { $v.data('mask', $v.text()); }
            $v.text(r.value).addClass('revealed');
            $icon.removeClass('bi-eye').addClass('bi-eye-slash');
            $v.data('timer', setTimeout(function () {
                $v.text($v.data('mask')).removeClass('revealed');
                $icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }, 30000));
        }).fail(function (x) { if (x.status !== 401) toast(T.error, 'error'); });
    });

    $(document).on('click', '[data-toggle-visibility]', function () {
        var $in = $($(this).data('toggle-visibility'));
        var show = $in.attr('type') === 'password';
        $in.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
    });
    $(document).on('click', '[data-toggle-blur]', function () {
        var $t = $($(this).data('toggle-blur'));
        $t.toggleClass('masked');
        $(this).find('i').toggleClass('bi-eye', $t.hasClass('masked')).toggleClass('bi-eye-slash', !$t.hasClass('masked'));
    });

    /* ---------------- gerador e força de senha ---------------- */

    function randomInt(max) {
        var a = new Uint32Array(1), lim = Math.floor(0x100000000 / max) * max;
        do { crypto.getRandomValues(a); } while (a[0] >= lim);
        return a[0] % max;
    }
    function generatePassword(opts) {
        var lower = 'abcdefghijkmnopqrstuvwxyz', upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ', nums = '23456789', syms = '!@#$%^&*()-_=+[]{};:,.?/~';
        if (!opts.ambiguous) { lower += 'l'; upper += 'IO'; nums += '01'; }
        var sets = [lower, upper];
        if (opts.numbers) sets.push(nums);
        if (opts.symbols) sets.push(syms);
        var all = sets.join(''), out = [];
        sets.forEach(function (s) { out.push(s[randomInt(s.length)]); });
        while (out.length < opts.length) out.push(all[randomInt(all.length)]);
        for (var i = out.length - 1; i > 0; i--) { var j = randomInt(i + 1); var t = out[i]; out[i] = out[j]; out[j] = t; }
        return out.join('');
    }
    function genOpts() {
        return {
            length: parseInt($('#genLength').val() || 20, 10),
            symbols: $('#genSymbols').length ? $('#genSymbols').is(':checked') : true,
            numbers: $('#genNumbers').length ? $('#genNumbers').is(':checked') : true,
            ambiguous: $('#genAmbiguous').is(':checked')
        };
    }
    var lastGenTarget = null;
    $(document).on('click', '[data-generate]', function () {
        var $in = $($(this).data('generate'));
        lastGenTarget = $in;
        $in.val(generatePassword(genOpts())).attr('type', 'text').trigger('input');
        $('#genOptions').removeClass('d-none');
        var confirmField = $('#' + $in.attr('id') + '_confirmation');
        if (confirmField.length) confirmField.val($in.val()).attr('type', 'text');
    });
    $(document).on('input change', '#genLength, #genSymbols, #genNumbers, #genAmbiguous', function () {
        $('#genLengthVal').text($('#genLength').val());
        if (lastGenTarget) lastGenTarget.val(generatePassword(genOpts())).trigger('input');
    });

    function strengthScore(pw) {
        if (!pw) return -1;
        var classes = 0;
        if (/[a-z]/.test(pw)) classes++;
        if (/[A-Z]/.test(pw)) classes++;
        if (/\d/.test(pw)) classes++;
        if (/[^A-Za-z0-9]/.test(pw)) classes++;
        var pool = (/[a-z]/.test(pw) ? 26 : 0) + (/[A-Z]/.test(pw) ? 26 : 0) + (/\d/.test(pw) ? 10 : 0) + (/[^A-Za-z0-9]/.test(pw) ? 32 : 0);
        var bits = pw.length * Math.log2(Math.max(pool, 1));
        if (/(.)\1{2,}/.test(pw)) bits -= 10;
        if (/^(?:password|senha|contrase|123456|qwerty|admin)/i.test(pw)) bits -= 30;
        if (bits < 35) return 0;
        if (bits < 55) return 1;
        if (bits < 75 || classes < 3) return 2;
        if (bits < 100) return 3;
        return 4;
    }
    $(document).on('input', '[data-strength]', function () {
        var $m = $($(this).data('strength'));
        var s = strengthScore($(this).val());
        if (s < 0) { $m.removeAttr('data-score').find('.strength-label').text(''); return; }
        $m.attr('data-score', s).find('.strength-label').text((T.strength || [])[s] || '');
    });

    /* ---------------- confirmações ---------------- */

    var $confirmModal = null, pendingForm = null;
    function ensureConfirmModal() {
        if ($confirmModal) return $confirmModal;
        $confirmModal = $('<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body p-4 text-center"><i class="bi bi-exclamation-triangle text-warning fs-1"></i><p class="mt-3 mb-0" data-msg></p></div><div class="modal-footer justify-content-center border-0 pt-0"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal"></button><button type="button" class="btn btn-danger" data-ok>OK</button></div></div></div></div>');
        $confirmModal.find('[data-bs-dismiss]').text(T.cancel || 'Cancel');
        $('body').append($confirmModal);
        $confirmModal.on('click', '[data-ok]', function () {
            bootstrap.Modal.getInstance($confirmModal[0]).hide();
            if (pendingForm) { pendingForm.dataset.confirmed = '1'; pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit(); }
        });
        return $confirmModal;
    }
    $(document).on('submit', 'form[data-confirm]', function (e) {
        if (this.dataset.confirmed === '1') { return; }
        e.preventDefault();
        pendingForm = this;
        var $m = ensureConfirmModal();
        $m.find('[data-msg]').text($(this).data('confirm') || T.confirm);
        bootstrap.Modal.getOrCreateInstance($m[0]).show();
    });

    /* ---------------- diversos ---------------- */

    $(document).on('change', '[data-enable]', function () {
        var $t = $($(this).data('enable'));
        var on = $(this).is(':checked');
        $t.prop('disabled', !on).toggleClass('disabled', !on).attr('aria-disabled', on ? 'false' : 'true');
    });

    $(document).on('click', '[data-download-text]', function () {
        var blob = new Blob([$(this).attr('data-download-text')], { type: 'text/plain;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = $(this).data('filename') || 'download.txt';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    });

    $(document).on('change', 'input[data-auto-submit]', function () {
        if (this.files && this.files.length) { $(this).closest('form').trigger('submit'); }
    });
    $(document).on('dragover dragenter', '.dropzone', function (e) { e.preventDefault(); $(this).addClass('dragover'); });
    $(document).on('dragleave drop', '.dropzone', function (e) { e.preventDefault(); $(this).removeClass('dragover'); });
    $(document).on('drop', '.dropzone', function (e) {
        var files = e.originalEvent.dataTransfer.files;
        var input = $(this).find('input[type=file]')[0];
        if (files && files.length && input) {
            try { input.files = files; } catch (err) { return; }
            $(input).closest('form').trigger('submit');
        }
    });

    /* QR do 2FA */
    $(function () {
        $('[data-qr]').each(function () {
            if (typeof qrcode !== 'function') return;
            var qr = qrcode(0, 'M');
            qr.addData($(this).attr('data-qr'));
            qr.make();
            this.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
        });
    });

    /* ---------------- formulário de item ---------------- */

    var $form = $('#itemForm');
    if ($form.length) {
        var fields = $form.data('fields') || {};
        var rotatable = $form.data('rotatable') || [];
        var applyType = function () {
            var type = $form.find('input[name=type]:checked').val() || $form.find('input[name=type]').val();
            var show = fields[type] || [];
            $form.find('[data-field]').each(function () {
                $(this).toggleClass('d-none', show.indexOf($(this).data('field')) === -1);
            });
            $form.find('label[data-label-default]').each(function () {
                var l = $(this).attr('data-label-' + type) || $(this).attr('data-label-default');
                $(this).text(l);
            });
            $('#rotationField').toggleClass('d-none', rotatable.indexOf(type) === -1);
        };
        $form.on('change', 'input[name=type]', applyType);
        applyType();
    }

    /* ---------------- filtros do cofre ---------------- */

    var $rows = $('.vault-table tbody tr');
    if ($rows.length) {
        var filter = function () {
            var q = ($('#filterSearch').val() || '').toLowerCase().trim();
            var type = $('#filterType').val(), tag = $('#filterTag').val(), scope = $('#filterScope').val();
            var visible = 0;
            $rows.each(function () {
                var $r = $(this);
                var ok = (!q || String($r.data('search')).indexOf(q) !== -1)
                    && (!type || $r.data('type') === type)
                    && (!tag || String($r.data('tags')).indexOf('|' + tag + '|') !== -1)
                    && (!scope || (' ' + $r.data('scope') + ' ').indexOf(' ' + scope + ' ') !== -1);
                $r.toggleClass('d-none', !ok);
                if (ok) visible++;
            });
            $('#noResults').toggleClass('d-none', visible > 0);
        };
        $('#filterSearch').on('input', filter);
        $('#filterType, #filterTag, #filterScope').on('change', filter);
        $(document).on('keydown', function (e) {
            if (e.key === '/' && !$(e.target).is('input, textarea, select')) { e.preventDefault(); $('#filterSearch').trigger('focus'); }
        });
    }

    /* ---------------- bloqueio por inatividade ---------------- */

    if (CFG.authenticated && CFG.idleMinutes > 0) {
        var lastActivity = Date.now(), lastPing = Date.now(), warned = false;
        var idleMs = CFG.idleMinutes * 60000, warnMs = Math.max(idleMs - 60000, idleMs * 0.8);
        $(document).on('mousemove keydown scroll click touchstart', function () { if (!warned) lastActivity = Date.now(); });
        var lockNow = function () {
            var f = $('<form method="POST"></form>').attr('action', CFG.urls.logout);
            f.append($('<input type="hidden" name="_token">').val(CFG.csrf));
            $('body').append(f); f.trigger('submit');
        };
        setInterval(function () {
            var idle = Date.now() - lastActivity;
            if (idle >= idleMs) { lockNow(); return; }
            if (idle >= warnMs && !warned) {
                warned = true;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('idleModal')).show();
            }
            // mantém a sessão viva enquanto há atividade real na página
            if (!warned && Date.now() - lastPing > 4 * 60000 && idle < 60000 && CFG.urls.ping) {
                lastPing = Date.now();
                $.post(CFG.urls.ping);
            }
        }, 5000);
        $(document).on('click', '[data-idle-stay]', function () {
            warned = false; lastActivity = Date.now(); lastPing = Date.now();
            $.post(CFG.urls.ping);
            bootstrap.Modal.getInstance(document.getElementById('idleModal')).hide();
        });
    }

    /* ---------------- passkeys (WebAuthn) ---------------- */

    function b64ToBuf(b64) {
        b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        var bin = atob(b64), buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }
    function bufToB64(buf) {
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin);
    }
    function decodeBinary(obj) {
        if (typeof obj === 'string' && obj.indexOf('=?BINARY?B?') === 0) {
            return b64ToBuf(obj.substring(11, obj.length - 2));
        }
        if (Array.isArray(obj)) return obj.map(decodeBinary);
        if (obj && typeof obj === 'object') {
            Object.keys(obj).forEach(function (k) { obj[k] = decodeBinary(obj[k]); });
        }
        return obj;
    }
    var webauthnOk = !!(window.PublicKeyCredential && navigator.credentials);
    function showErr($box, msg) { $box.text(msg).removeClass('d-none'); }

    $('#addPasskey').on('click', function () {
        var $b = $(this), $err = $('#passkeyError').addClass('d-none');
        if (!webauthnOk) { showErr($err, T.passkey_unsupported); return; }
        var name = window.prompt(T.passkey_name_prompt || 'Name', navigator.platform || 'Passkey');
        if (!name) return;
        $b.prop('disabled', true);
        $.post($b.data('options')).done(function (opts) {
            navigator.credentials.create(decodeBinary(opts)).then(function (cred) {
                return $.post($b.data('store'), {
                    name: name,
                    clientDataJSON: bufToB64(cred.response.clientDataJSON),
                    attestationObject: bufToB64(cred.response.attestationObject)
                });
            }).then(function () {
                toast(T.passkey_added); setTimeout(function () { window.location.reload(); }, 600);
            }).catch(function (e) {
                showErr($err, (e && e.responseJSON && e.responseJSON.message) || (e && e.message) || T.error);
                $b.prop('disabled', false);
            });
        }).fail(function () { showErr($err, T.error); $b.prop('disabled', false); });
    });

    $('#passkeyLogin').on('click', function () {
        var $b = $(this), $err = $('#passkeyError').addClass('d-none');
        if (!webauthnOk) { showErr($err, T.passkey_unsupported); return; }
        $b.prop('disabled', true);
        $.post($b.data('options')).done(function (opts) {
            navigator.credentials.get(decodeBinary(opts)).then(function (cred) {
                return $.post($b.data('verify'), {
                    id: bufToB64(cred.rawId),
                    clientDataJSON: bufToB64(cred.response.clientDataJSON),
                    authenticatorData: bufToB64(cred.response.authenticatorData),
                    signature: bufToB64(cred.response.signature),
                    userHandle: cred.response.userHandle ? bufToB64(cred.response.userHandle) : null
                });
            }).then(function (r) {
                window.location.href = r.redirect;
            }).catch(function (e) {
                showErr($err, (e && e.responseJSON && e.responseJSON.message) || (e && e.message) || T.error);
                $b.prop('disabled', false);
            });
        }).fail(function () { showErr($err, T.error); $b.prop('disabled', false); });
    });

    /* tooltips */
    $('[title]').each(function () { if ($(this).is('button, a, i')) { try { new bootstrap.Tooltip(this, { trigger: 'hover' }); } catch (e) {} } });

})(jQuery);
