/**
 * SG MailSmart Trials Engine – Admin JavaScript
 */
(function ($) {
    'use strict';

    var config = window.mailsmartTrials || {};

    /* ─── Tab Navigation ──────────────────────────────────────── */
    $(document).on('click', '.mailsmart-trials-tabs .nav-tab', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        $('.mailsmart-trials-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.mailsmart-trials-tab-content').hide();
        $('#tab-' + tab).show();
    });

    /* ─── Populate User Selects ───────────────────────────────── */
    function populateUserSelects() {
        var users = config.users || [];
        var selects = ['#sandbox-user-select', '#demo-user-select'];

        selects.forEach(function (sel) {
            var $select = $(sel);
            $select.find('option:not(:first)').remove();
            users.forEach(function (u) {
                $select.append(
                    $('<option>').val(u.id).text(u.name + ' (' + u.email + ')')
                );
            });
        });
    }

    /* ─── Notification ────────────────────────────────────────── */
    function showNotice(message, isError) {
        var $notice = $('#mailsmart-trials-notice');
        $notice.text(message)
            .toggleClass('error', !!isError)
            .show();

        setTimeout(function () {
            $notice.fadeOut();
        }, 4000);
    }

    /* ─── Settings Form ───────────────────────────────────────── */
    $('#mailsmart-trials-settings-form').on('submit', function (e) {
        e.preventDefault();

        var formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'mailsmart_trials_save_settings' });
        formData.push({ name: 'nonce', value: config.nonce });

        // Include sandbox/demo checkboxes that live outside this form.
        if ($('#sandbox-enabled').is(':checked')) {
            formData.push({ name: 'sandbox_enabled', value: '1' });
        }
        if ($('#demo-enabled').is(':checked')) {
            formData.push({ name: 'demo_enabled', value: '1' });
        }

        $.post(config.ajaxUrl, formData, function (response) {
            if (response.success) {
                showNotice(config.i18n.saved);
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        }).fail(function () {
            showNotice(config.i18n.error, true);
        });
    });

    /* ─── Trial Type Toggle ───────────────────────────────────── */
    function toggleTrialTypeRows() {
        var type = $('#trial-type-select').val();
        if (type === 'time' || type === 'feature') {
            $('.trial-time-row').show();
            $('.trial-usage-row').hide();
        } else if (type === 'usage') {
            $('.trial-time-row').hide();
            $('.trial-usage-row').show();
        } else {
            // hybrid
            $('.trial-time-row').show();
            $('.trial-usage-row').show();
        }
    }

    $('#trial-type-select').on('change', toggleTrialTypeRows);

    /* ─── Sandbox Controls ────────────────────────────────────── */
    $('#sandbox-start').on('click', function () {
        var userId = $('#sandbox-user-select').val();
        if (!userId) {
            showNotice(config.i18n.error, true);
            return;
        }

        $.post(config.ajaxUrl, {
            action: 'mailsmart_trials_start',
            nonce: config.nonce,
            user_id: userId,
            mode: 'sandbox'
        }, function (response) {
            if (response.success) {
                showNotice(config.i18n.started);
                refreshDashboard();
                startTimer(response.data.data);
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        }).fail(function () {
            showNotice(config.i18n.error, true);
        });
    });

    $('#sandbox-stop').on('click', function () {
        var userId = $('#sandbox-user-select').val();
        if (!userId) {
            showNotice(config.i18n.error, true);
            return;
        }

        if (!confirm(config.i18n.confirmStop)) {
            return;
        }

        $.post(config.ajaxUrl, {
            action: 'mailsmart_trials_stop',
            nonce: config.nonce,
            user_id: userId
        }, function (response) {
            if (response.success) {
                showNotice(config.i18n.stopped);
                refreshDashboard();
                stopTimer();
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        }).fail(function () {
            showNotice(config.i18n.error, true);
        });
    });

    /* ─── Demo Controls ───────────────────────────────────────── */
    $('#demo-load').on('click', function () {
        $.post(config.ajaxUrl, {
            action: 'mailsmart_trials_load_demo',
            nonce: config.nonce
        }, function (response) {
            if (response.success) {
                showNotice(config.i18n.demoLoaded);
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        }).fail(function () {
            showNotice(config.i18n.error, true);
        });
    });

    $('#demo-start').on('click', function () {
        var userId = $('#demo-user-select').val();
        if (!userId) {
            showNotice(config.i18n.error, true);
            return;
        }

        $.post(config.ajaxUrl, {
            action: 'mailsmart_trials_start',
            nonce: config.nonce,
            user_id: userId,
            mode: 'demo'
        }, function (response) {
            if (response.success) {
                showNotice(config.i18n.started);
                refreshDashboard();
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        }).fail(function () {
            showNotice(config.i18n.error, true);
        });
    });

    /* ─── Timer ───────────────────────────────────────────────── */
    var timerInterval = null;
    var timerSeconds = 0;

    function startTimer(record) {
        stopTimer();
        if (!record || !record.remaining_seconds) {
            return;
        }
        timerSeconds = parseInt(record.remaining_seconds, 10);
        updateTimerDisplay();
        timerInterval = setInterval(function () {
            timerSeconds--;
            if (timerSeconds <= 0) {
                stopTimer();
                $('#sandbox-timer').text('00:00:00');
                return;
            }
            updateTimerDisplay();
        }, 1000);
    }

    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        $('#sandbox-timer').text('--:--:--');
    }

    function updateTimerDisplay() {
        var h = Math.floor(timerSeconds / 3600);
        var m = Math.floor((timerSeconds % 3600) / 60);
        var s = timerSeconds % 60;
        $('#sandbox-timer').text(
            pad(h) + ':' + pad(m) + ':' + pad(s)
        );
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    /* ─── Dashboard ───────────────────────────────────────────── */
    function refreshDashboard() {
        $.ajax({
            url: config.restUrl + 'active-trials',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.restNonce);
            },
            success: function (response) {
                if (response.success) {
                    renderDashboard(response.data);
                }
            }
        });
    }

    function renderDashboard(trials) {
        var $body = $('#trials-dashboard-body');
        $body.empty();

        if (!trials || trials.length === 0) {
            $body.append('<tr class="no-items"><td colspan="7">' + escHtml(config.i18n.noTrials || 'No active trials.') + '</td></tr>');
            return;
        }

        trials.forEach(function (t) {
            var remaining = formatTime(t.remaining_seconds || t.remaining || 0);
            var modeBadge = '<span class="badge badge-' + t.mode + '">' + t.mode + '</span>';
            var statusBadge = t.paused
                ? '<span class="badge badge-paused">Paused</span>'
                : '<span class="badge badge-active">Active</span>';

            var usageHtml = '';
            var usage = t.usage_consumed || t.usage || {};
            var limits = t.usage_limits || t.limits || {};
            ['emails', 'ai', 'automation'].forEach(function (key) {
                var used = usage[key] || 0;
                var limit = limits[key] || 0;
                if (limit > 0) {
                    var pct = Math.min(100, Math.round((used / limit) * 100));
                    var cls = pct > 90 ? 'danger' : pct > 70 ? 'warning' : '';
                    usageHtml += '<div class="usage-bar">' +
                        '<span>' + key + ':</span>' +
                        '<div class="usage-bar-track"><div class="usage-bar-fill ' + cls + '" style="width:' + pct + '%"></div></div>' +
                        '<span>' + used + '/' + limit + '</span></div>';
                }
            });

            if (!usageHtml) {
                usageHtml = '—';
            }

            $body.append(
                '<tr>' +
                '<td>' + escHtml(t.display_name) + '<br><small>' + escHtml(t.email) + '</small></td>' +
                '<td>' + modeBadge + '</td>' +
                '<td>' + escHtml(t.trial_type) + '</td>' +
                '<td>' + remaining + '</td>' +
                '<td>' + usageHtml + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td><button class="button button-small stop-trial-btn" data-userid="' + t.user_id + '">Stop</button></td>' +
                '</tr>'
            );
        });
    }

    $(document).on('click', '.stop-trial-btn', function () {
        var userId = $(this).data('userid');
        if (!confirm(config.i18n.confirmStop)) {
            return;
        }

        $.post(config.ajaxUrl, {
            action: 'mailsmart_trials_stop',
            nonce: config.nonce,
            user_id: userId
        }, function (response) {
            if (response.success) {
                showNotice(config.i18n.stopped);
                refreshDashboard();
            } else {
                showNotice(response.data.message || config.i18n.error, true);
            }
        });
    });

    function formatTime(seconds) {
        if (seconds <= 0) return '00:00:00';
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;

        if (d > 0) {
            return d + 'd ' + pad(h) + 'h ' + pad(m) + 'm';
        }
        return pad(h) + ':' + pad(m) + ':' + pad(s);
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ─── Initialize ──────────────────────────────────────────── */
    $(document).ready(function () {
        populateUserSelects();
        toggleTrialTypeRows();
        renderDashboard(config.trials || []);
    });

})(jQuery);
