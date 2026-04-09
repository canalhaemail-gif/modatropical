(function () {
    'use strict';

    var config = window.messageBatchStatusConfig || null;

    if (!config || typeof config !== 'object') {
        return;
    }

    var root = document.querySelector('[data-message-batch-root]');

    if (!root) {
        return;
    }

    var endpointUrl = String(config.endpointUrl || '').trim();
    var pollIntervalMs = Number.parseInt(String(config.pollIntervalMs || '5000'), 10);
    var state = {
        batch: config.batch && typeof config.batch === 'object' ? config.batch : null,
        timerId: 0,
        isRefreshing: false
    };

    if (!endpointUrl || Number.isNaN(pollIntervalMs) || pollIntervalMs < 1000) {
        return;
    }

    var statusBadge = root.querySelector('[data-message-batch-status-badge]');
    var publicIdNode = root.querySelector('[data-message-batch-public-id]');
    var metaNode = root.querySelector('[data-message-batch-meta]');
    var meterWrap = root.querySelector('[data-message-batch-meter-wrap]');
    var percentNode = root.querySelector('[data-message-batch-percent]');
    var barNode = root.querySelector('[data-message-batch-bar]');
    var countsWrap = root.querySelector('[data-message-batch-counts-wrap]');
    var emptyNode = root.querySelector('[data-message-batch-empty]');
    var errorsWrap = root.querySelector('[data-message-batch-errors-wrap]');
    var errorsList = root.querySelector('[data-message-batch-errors-list]');

    var countNodes = {};
    root.querySelectorAll('[data-message-batch-count]').forEach(function (node) {
        countNodes[String(node.getAttribute('data-message-batch-count') || '')] = node;
    });

    var isTerminalStatus = function (status) {
        return [
            'completed',
            'completed_with_failures',
            'failed',
            'cancelled'
        ].indexOf(String(status || '')) !== -1;
    };

    var buildMetaLabel = function (batch) {
        if (!batch || typeof batch !== 'object') {
            return 'Quando voce criar um novo lote, o progresso vai aparecer aqui sem precisar recarregar para acompanhar.';
        }

        if (batch.is_terminal && batch.finished_at_label) {
            return 'Encerrado em ' + String(batch.finished_at_label) + '.';
        }

        if (batch.started_at_label) {
            return 'Em processamento desde ' + String(batch.started_at_label) + '.';
        }

        if (batch.created_at_label) {
            return 'Aguardando worker desde ' + String(batch.created_at_label) + '.';
        }

        return 'Lote aguardando atualizacao.';
    };

    var setStatusTone = function (tone) {
        if (!statusBadge) {
            return;
        }

        statusBadge.classList.remove(
            'is-queued',
            'is-processing',
            'is-success',
            'is-warning',
            'is-danger',
            'is-muted'
        );
        statusBadge.classList.add('is-' + String(tone || 'muted'));
    };

    var renderErrors = function (errors) {
        if (!errorsWrap || !errorsList) {
            return;
        }

        errorsList.innerHTML = '';

        if (!Array.isArray(errors) || errors.length === 0) {
            errorsWrap.hidden = true;
            return;
        }

        errors.forEach(function (row) {
            if (!row || typeof row !== 'object') {
                return;
            }

            var item = document.createElement('li');
            var title = document.createElement('strong');
            var meta = document.createElement('span');
            var metaParts = [];

            title.textContent = String(row.summary || 'Erro sem resumo');

            if (row.created_at_label) {
                metaParts.push(String(row.created_at_label));
            }
            if (row.channel) {
                metaParts.push(String(row.channel));
            }
            if (row.stage) {
                metaParts.push(String(row.stage));
            }
            if (row.error_code) {
                metaParts.push(String(row.error_code));
            }

            meta.textContent = metaParts.join(' | ');
            item.appendChild(title);
            item.appendChild(meta);
            errorsList.appendChild(item);
        });

        errorsWrap.hidden = errorsList.children.length === 0;
    };

    var renderBatch = function (batch) {
        state.batch = batch && typeof batch === 'object' ? batch : null;

        if (!state.batch) {
            if (publicIdNode) {
                publicIdNode.textContent = 'Nenhum lote recente';
            }
            if (metaNode) {
                metaNode.textContent = buildMetaLabel(null);
            }
            if (statusBadge) {
                statusBadge.textContent = 'Sem lote';
            }
            setStatusTone('muted');
            if (meterWrap) {
                meterWrap.hidden = true;
            }
            if (countsWrap) {
                countsWrap.hidden = true;
            }
            if (emptyNode) {
                emptyNode.hidden = false;
            }
            renderErrors([]);
            return;
        }

        if (publicIdNode) {
            publicIdNode.textContent = String(state.batch.public_id || 'Lote sem id');
        }
        if (metaNode) {
            metaNode.textContent = buildMetaLabel(state.batch);
        }
        if (statusBadge) {
            statusBadge.textContent = String(state.batch.status_label || 'Atualizando');
        }
        setStatusTone(String(state.batch.status_tone || 'queued'));

        if (meterWrap) {
            meterWrap.hidden = false;
        }
        if (countsWrap) {
            countsWrap.hidden = false;
        }
        if (emptyNode) {
            emptyNode.hidden = true;
        }

        var percent = Number.parseInt(String(state.batch.completion_percent || 0), 10);

        if (Number.isNaN(percent) || percent < 0) {
            percent = 0;
        } else if (percent > 100) {
            percent = 100;
        }

        if (percentNode) {
            percentNode.textContent = String(percent) + '%';
        }
        if (barNode) {
            barNode.style.width = String(percent) + '%';
        }

        var counts = state.batch.counts && typeof state.batch.counts === 'object'
            ? state.batch.counts
            : {};

        Object.keys(countNodes).forEach(function (key) {
            countNodes[key].textContent = String(Number.parseInt(String(counts[key] || 0), 10) || 0);
        });

        renderErrors(Array.isArray(state.batch.recent_errors) ? state.batch.recent_errors : []);
    };

    var stopPolling = function () {
        if (state.timerId) {
            window.clearInterval(state.timerId);
            state.timerId = 0;
        }
    };

    var fetchProgress = async function () {
        if (state.isRefreshing || document.hidden) {
            return;
        }

        if (!state.batch || !state.batch.public_id) {
            stopPolling();
            return;
        }

        state.isRefreshing = true;

        try {
            var url = new URL(endpointUrl, window.location.href);
            url.searchParams.set('public_id', String(state.batch.public_id));

            var response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) {
                if (response.status === 404) {
                    renderBatch(null);
                    stopPolling();
                }
                return;
            }

            var payload = await response.json();

            if (!payload || payload.ok !== true || !payload.batch) {
                return;
            }

            renderBatch(payload.batch);

            if (isTerminalStatus(payload.batch.status)) {
                stopPolling();
            }
        } catch (error) {
            return;
        } finally {
            state.isRefreshing = false;
        }
    };

    var startPolling = function () {
        stopPolling();

        if (!state.batch || !state.batch.public_id || isTerminalStatus(state.batch.status)) {
            return;
        }

        state.timerId = window.setInterval(fetchProgress, pollIntervalMs);
    };

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            fetchProgress();
        }
    });

    renderBatch(state.batch);
    startPolling();
}());
