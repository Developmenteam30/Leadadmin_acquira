<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8"/>
    <title>Feed Form - <?php echo e($companyName); ?></title>
    <style type="text/css">
        body { font-family: Verdana, sans-serif; margin: 20px; background: #f8fafc; color: #111827; }
        h1, h2, h3, h4, h5, p { margin-top: 0; }
        code { background: #eef2f7; padding: 2px 6px; border-radius: 6px; }
        .layout { display: flex; gap: 20px; align-items: flex-start; }
        .left-panel, .right-panel {
            background: #fff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .left-panel { flex: 0 0 42%; }
        .right-panel { flex: 1; }
        .field { margin: 0 0 12px; }
        .field label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .field input, .field select {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            background: #fff;
        }
        .req { color: #b91c1c; margin-left: 6px; }
        .hint { font-size: 11px; color: #64748b; margin-top: 4px; }
        .action-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .btn {
            padding: 10px 14px;
            border: 1px solid #0f172a;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
        }
        .btn.secondary {
            background: #fff;
            color: #111827;
            border-color: #cbd5e1;
        }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .status-line { margin: 0 0 8px; font-size: 12px; font-weight: bold; }
        pre {
            margin: 0;
            min-height: 420px;
            background: #0b1020;
            color: #e6edf3;
            padding: 14px;
            border-radius: 8px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .subtle { font-size: 12px; color: #475569; }
        .section-spacer { margin-top: 16px; }
    </style>
</head>
<body>
<?php
    $requiresPhone = in_array('phone', $requiredArray, true);
?>

<h1><?php echo e($appName); ?></h1>
<h2>Feed Form</h2>
<p><a href="<?php echo e($apiSpecUrl); ?>" target="_blank" rel="noopener noreferrer">Open API spec</a></p>
<h3>Company: <?php echo e($companyName); ?> (Feed: <?php echo e($feed->idFeedIn); ?>)</h3>
<p class="subtle">Use this form to submit lead data and review the live response on the right.</p>
<p><strong>API URL:</strong> <code><?php echo e($apiUrl); ?></code></p>

<div class="layout">
    <div class="left-panel">
        <?php if($feed->feedCategory === 'phone-preping'): ?>
            <h4>PING request</h4>
            <form id="pingForm" autocomplete="off">
                <input type="hidden" name="ping" value="1"/>

                <?php $__currentLoopData = array_filter($allowedPingArray); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allowed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($allowed === 'pswd'): ?>
                        <div class="field">
                            <label for="pswdPing">pswd<span class="req">*</span></label>
                            <input id="pswdPing" name="pswd" type="password" value="" />
                        </div>
                    <?php elseif($allowed !== 'ping'): ?>
                        <div class="field">
                            <label for="ping_<?php echo e($allowed); ?>">
                                <?php echo e($allowed); ?>

                                <?php if(in_array($allowed, $requiredArray, true)): ?>
                                    <span class="req">*</span>
                                <?php endif; ?>
                            </label>
                            <input id="ping_<?php echo e($allowed); ?>" name="<?php echo e($allowed); ?>" type="text" value="" />
                            <?php if(!empty($findField($allowed, 'fieldDescription'))): ?>
                                <div class="hint"><?php echo e($findField($allowed, 'fieldDescription')); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <div class="field">
                    <label for="pingOutFormat">outFormat</label>
                    <select id="pingOutFormat" name="outFormat">
                        <option value="xml" selected>xml</option>
                        <option value="json">json</option>
                    </select>
                </div>

                <div class="action-row">
                    <button class="btn" type="submit" id="pingBtn">Send PING</button>
                </div>
            </form>

            <div class="section-spacer"></div>
        <?php endif; ?>

        <h4>Submit lead</h4>
        <form id="postForm" autocomplete="off">
            <?php $__currentLoopData = array_filter($allowedArray); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allowed): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($allowed === 'pswd'): ?>
                    <div class="field">
                        <label for="pswdPost">pswd<span class="req">*</span></label>
                        <input id="pswdPost" name="pswd" type="password" value="" />
                    </div>
                <?php elseif($allowed !== 'ping'): ?>
                    <div class="field">
                        <label for="post_<?php echo e($allowed); ?>">
                            <?php echo e($allowed); ?>

                            <?php if(in_array($allowed, $requiredArray, true) || (($allowed === 'landline' || $allowed === 'cellphone') && $requiresPhone)): ?>
                                <span class="req">*</span>
                            <?php endif; ?>
                        </label>
                        <input id="post_<?php echo e($allowed); ?>" name="<?php echo e($allowed); ?>" type="text" value="" />
                        <?php if(!empty($findField($allowed, 'fieldDescription'))): ?>
                            <div class="hint"><?php echo e($findField($allowed, 'fieldDescription')); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <div class="field">
                <label for="postOutFormat">outFormat</label>
                <select id="postOutFormat" name="outFormat">
                    <option value="xml" selected>xml</option>
                    <option value="json">json</option>
                </select>
            </div>

            <div class="action-row">
                <button class="btn" type="submit" id="postBtn">Submit lead</button>
                <button class="btn secondary" type="button" id="clearBtn">Clear form</button>
            </div>
        </form>
    </div>

    <div class="right-panel">
        <h4>Response</h4>
        <div class="status-line" id="statusLine">Waiting for request...</div>
        <pre id="responseBox">(no response yet)</pre>
    </div>
</div>

<script>
    (function () {
        const apiUrl = <?php echo json_encode($apiUrl, 15, 512) ?>;
        const requiredArray = <?php echo json_encode($requiredArray, 15, 512) ?>;

        const responseBox = document.getElementById('responseBox');
        const statusLine = document.getElementById('statusLine');

        function setResponse(text, status, contentType) {
            let display = text;
            const looksJson = (contentType && contentType.toLowerCase().includes('application/json')) || (text.trim().startsWith('{') || text.trim().startsWith('['));
            if (looksJson) {
                try {
                    display = JSON.stringify(JSON.parse(text), null, 2);
                } catch (e) {}
            }
            responseBox.textContent = display || '';
            statusLine.textContent = 'HTTP ' + status;
        }

        function getOutFormatFromForm(form) {
            const el = form.querySelector('select[name="outFormat"], input[name="outFormat"]');
            return (el && el.value) ? el.value : 'xml';
        }

        function validateRequiredFields(form, requiredFields) {
            for (const field of requiredFields) {
                if (field === 'pswd') {
                    const pswdInput = form.querySelector('input[name="pswd"]');
                    if (!pswdInput || !String(pswdInput.value || '').trim()) return 'pswd is required.';
                    continue;
                }

                if (field === 'phone') {
                    const landline = String(form.querySelector('input[name="landline"]')?.value || '').trim();
                    const cellphone = String(form.querySelector('input[name="cellphone"]')?.value || '').trim();
                    if (!landline && !cellphone) return 'Phone (landline or cellphone) is required.';
                    continue;
                }

                const input = form.querySelector('[name="' + field + '"]');
                if (!input) continue;
                if (!String(input.value || '').trim()) return field + ' is a required field.';
            }
            return null;
        }

        async function sendForm(form) {
            const invalidMsg = validateRequiredFields(form, requiredArray);
            if (invalidMsg) {
                statusLine.textContent = 'Validation error';
                responseBox.textContent = invalidMsg;
                return;
            }

            const outFormat = getOutFormatFromForm(form);
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                statusLine.textContent = 'Sending...';
                responseBox.textContent = '';

                const formData = new FormData(form);
                const params = new URLSearchParams();
                for (const [k, v] of formData.entries()) {
                    if (typeof v === 'string' && v.trim() === '') continue;
                    if (v === undefined || v === null) continue;
                    params.set(k, String(v));
                }

                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });

                const text = await res.text();
                const contentType = res.headers.get('content-type') || '';
                setResponse(text, res.status, contentType);

                const pingForm = document.getElementById('pingForm');
                const postForm = document.getElementById('postForm');
                if (pingForm && postForm && form === pingForm) {
                    let authorization = null;
                    if (outFormat === 'json' || (contentType && contentType.toLowerCase().includes('application/json'))) {
                        try {
                            const data = JSON.parse(text);
                            authorization = data && (data.authorization || data.Authorization);
                        } catch (e) {}
                    } else {
                        const match = text.match(/<authorization>([^<]*)<\/authorization>/i);
                        if (match && match[1]) authorization = match[1];
                    }
                    if (authorization) {
                        const authInput = postForm.querySelector('[name="authorization"]');
                        if (authInput) authInput.value = authorization;
                    }
                }
            } catch (e) {
                statusLine.textContent = 'Request failed';
                responseBox.textContent = String(e && e.message ? e.message : e);
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        const pingForm = document.getElementById('pingForm');
        if (pingForm) {
            pingForm.addEventListener('submit', function (e) {
                e.preventDefault();
                sendForm(pingForm);
            });

            const pswdPing = document.getElementById('pswdPing');
            const pswdPost = document.getElementById('pswdPost');
            if (pswdPing && pswdPost) {
                pswdPing.addEventListener('input', function () { pswdPost.value = pswdPing.value; });
                pswdPost.addEventListener('input', function () { pswdPing.value = pswdPost.value; });
            }
        }

        const postForm = document.getElementById('postForm');
        if (postForm) {
            postForm.addEventListener('submit', function (e) {
                e.preventDefault();
                sendForm(postForm);
            });
        }

        const clearBtn = document.getElementById('clearBtn');
        if (clearBtn && postForm) {
            clearBtn.addEventListener('click', function () {
                postForm.reset();
                if (pingForm) pingForm.reset();
                statusLine.textContent = 'Waiting for request...';
                responseBox.textContent = '(no response yet)';
            });
        }
    })();
</script>
</body>
</html>
<?php /**PATH /Users/raghunathsingh/raghu/Leadadmin_acquira/laravel/resources/views/live/feedform.blade.php ENDPATH**/ ?>