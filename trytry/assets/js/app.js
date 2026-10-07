/* ---------- Modals ---------- */
function openModal(id) { var m = document.getElementById(id); if (m) { m.classList.add('open'); } }
function closeModal(id) { var m = document.getElementById(id); if (m) { m.classList.remove('open'); } }
document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) { e.target.classList.remove('open'); }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    }
});

/* ---------- Helpers ---------- */
function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

/* ---------- Table search: <input data-table-search="tableId"> ---------- */
document.querySelectorAll('[data-table-search]').forEach(function (input) {
    input.addEventListener('input', function () {
        var table = document.getElementById(input.getAttribute('data-table-search'));
        if (!table) { return; }
        var q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
        });
    });
});

/* ---------- Mobile sidebar ---------- */
(function () {
    var btn = document.getElementById('mobileSidebarToggle');
    var side = document.querySelector('.sidebar');
    if (btn && side) { btn.addEventListener('click', function () { side.classList.toggle('open'); }); }
})();


/* ---------- Solicitation letter (preview + DOCX download) ---------- */

// Ensure root relative path to your image
var SCHOOL_LOGO_URL = '/trytry/assets/images/bcclogo.png';

// Folder where organization logos are stored (used when the database only saves a file name).
// CHANGE THIS to match your project's upload folder.
var ORG_LOGO_DIR = '/trytry/assets/uploads/org_logos/';

// Returns the logo URL of the organization that made the request.
function getOrgLogoUrl(req) {
    var keys = ['org_logo', 'logo', 'organization_logo', 'org_logo_path', 'logo_path', 'logo_url', 'orglogo', 'org_logo_url'];
    var v = '';
    for (var i = 0; i < keys.length; i++) {
        if (req && req[keys[i]]) { v = String(req[keys[i]]); break; }
    }
    if (!v) {
        console.warn('No org logo found on this request. Available fields:', req ? Object.keys(req) : req);
        return '';
    }
    if (/^(https?:)?\/\//.test(v) || v.indexOf('data:') === 0 || v.charAt(0) === '/') { return v; }
    v = v.replace(/^(\.\.?\/)+/, '');
    if (v.indexOf('/') > -1) { return '/trytry/' + v; }
    return ORG_LOGO_DIR + v;
}

function letterParagraphs(r) {
    var amount = 'PHP ' + Number(r.target_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
    return {
        date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
        control: r.control_no || '',
        salutation: 'To Whom It May Concern:',
        body: [
            'Greetings from ' + (r.org_name || '') + (r.acronym ? ' (' + r.acronym + ')' : '') + '!',
            'The organization is conducting "' + (r.event_name || '') + '" on or before ' + (r.date_needed || '') +
                '. Purpose: ' + (r.purpose || '') + '. The organization aims to raise ' + amount + ' for this activity.',
            'In this regard, we respectfully ask for your kind support through a donation in cash or in kind. ' +
                'This solicitation is authorized by the Office of Student Affairs (Control No. ' + (r.control_no || '') + ').',
            (r.admin_remarks ? 'Conditions: ' + r.admin_remarks : '')
        ].filter(Boolean),
        closing: 'Thank you very much for your generosity and support.',
        president: r.president || '', adviser: r.adviser || '',
        approver: r.approved_by || 'Office of Student Affairs'
    };
}

function openSolicitationLetterModal(req) {
    var L = letterParagraphs(req);
    var m = document.getElementById('letterModal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'letterModal';
        m.className = 'modal-overlay';
        document.body.appendChild(m);
    }
    window._currentLetterReq = req;

    // Fallbacks for logo paths
    var schoolLogoSrc = SCHOOL_LOGO_URL;
    var orgLogoSrc = getOrgLogoUrl(req);

    m.innerHTML =
        '<div class="modal-box modal-lg">' +
        '<div class="modal-header"><h3><i class="fa-solid fa-file-word"></i> Solicitation Letter</h3>' +
        '<button type="button" class="modal-close" onclick="closeModal(\'letterModal\')">&times;</button></div>' +
        '<div class="modal-body"><div class="letter-paper" style="padding: 30px; background: #fff;">' +
        
        '<!-- Header: school logo (left) | school name (center) | org logo (right) -->' +
        '<table style="width:100%; table-layout:fixed; border-collapse:collapse; margin-bottom:25px;">' +
        '  <tr>' +
        '    <td style="width:22%; text-align:left; vertical-align:middle;">' +
        '      <img src="' + schoolLogoSrc + '" alt="BCC Logo" style="height:85px; width:85px; object-fit:contain;" onerror="this.onerror=null; this.src=\'../assets/images/bcclogo.png\';">' +
        '    </td>' +
        '    <td style="width:56%; text-align:center; vertical-align:middle; font-family:\'Times New Roman\', serif; color:#000;">' +
        '      <div style="font-size:14pt; font-weight:bold; line-height:1.4;">Bacolod City College</div>' +
        '      <div style="font-size:11pt; line-height:1.4;">Office of Student Affairs</div>' +
        '      <div style="font-size:11pt; font-weight:bold; line-height:1.4;">' + (req.org_name || '') + '</div>' +
        '    </td>' +
        '    <td style="width:22%; text-align:right; vertical-align:middle;">' +
        (orgLogoSrc ? '      <img src="' + orgLogoSrc + '" alt="Org Logo" style="height:85px; width:85px; object-fit:contain;" onerror="console.error(\'Org logo failed to load:\', this.src);">' : '<div style="width:85px; height:85px; display:inline-block;"></div>') +
        '    </td>' +
        '  </tr>' +
        '</table>' +

        '<div style="font-family:\'Times New Roman\', serif; font-size:12pt; line-height:1.6; color:#000;">' +
        '  <p>' + L.date + '<br>Control No: ' + L.control + '</p>' +
        '  <p><strong>' + L.salutation + '</strong></p>' +
        L.body.map(function (p) { return '<p style="text-align:justify; text-indent:40px;">' + p + '</p>'; }).join('') +
        '  <p>' + L.closing + '</p><br>' +
        '  <p>Respectfully yours,<br><br><strong>' + L.president + '</strong><br>President, ' + (req.org_name || '') + '</p>' +
        '  <p>Noted by:<br><br><strong>' + L.adviser + '</strong><br>Adviser</p>' +
        '  <p>Approved by:<br><br><strong>' + L.approver + '</strong></p>' +
        '</div>' +

        '</div></div>' +
        '<div class="modal-footer"><button type="button" class="btn btn-outline-primary" onclick="closeModal(\'letterModal\')">Close</button>' +
        '<button type="button" class="btn btn-primary" onclick="downloadSolicitationDocx(window._currentLetterReq)"><i class="fa-solid fa-download"></i> Download DOCX</button></div>' +
        '</div>';
    
    openModal('letterModal');
}

/* ---------- Solicitation letter DOCX download ---------- */
// Uses the "docx" library, loaded on demand from a CDN the first time it's needed.
function loadDocxLib() {
    return new Promise(function (resolve, reject) {
        if (window.docx) { resolve(window.docx); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/docx@8.5.0/build/index.js';
        s.onload = function () { resolve(window.docx); };
        s.onerror = function () { reject(new Error('Could not load the DOCX library. Check your internet connection.')); };
        document.head.appendChild(s);
    });
}

// Fetch an image and return { data, type, width, height } scaled to fit a box (px), or null if unavailable.
async function fetchLogoForDocx(url, box) {
    if (!url) { return null; }
    try {
        var res = await fetch(url);
        if (!res.ok) { console.error('Logo not found (' + res.status + '):', url); return null; }
        var blob = await res.blob();
        var bmp = await createImageBitmap(blob);
        var scale = Math.min(box / bmp.width, box / bmp.height);
        var t = (blob.type || '').toLowerCase();
        var type = (t.indexOf('jpeg') > -1 || t.indexOf('jpg') > -1) ? 'jpg' : (t.indexOf('gif') > -1 ? 'gif' : 'png');
        return {
            data: await blob.arrayBuffer(),
            type: type,
            width: Math.round(bmp.width * scale),
            height: Math.round(bmp.height * scale)
        };
    } catch (e) { return null; }
}

async function downloadSolicitationDocx(req) {
    if (!req) { alert('No letter selected.'); return; }
    try {
        var d = await loadDocxLib();
        var L = letterParagraphs(req);
        var FONT = 'Times New Roman';
        var BOX = 80;

        var schoolLogo = await fetchLogoForDocx(SCHOOL_LOGO_URL, BOX);
        var orgLogo = await fetchLogoForDocx(getOrgLogoUrl(req), BOX);

        var noBorder = { style: d.BorderStyle.NONE, size: 0, color: 'FFFFFF' };
        var noBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };

        function run(text, opts) {
            opts = opts || {};
            return new d.TextRun({ text: text, font: FONT, size: opts.size || 24, bold: !!opts.bold });
        }
        function para(children, opts) {
            opts = opts || {};
            return new d.Paragraph({
                children: children,
                alignment: opts.align || d.AlignmentType.LEFT,
                spacing: { after: opts.after == null ? 200 : opts.after, line: 276 },
                indent: opts.indent ? { firstLine: 720 } : undefined
            });
        }
        function logoCell(logo, align, width) {
            var kids = logo ? [new d.Paragraph({
                alignment: align,
                children: [new d.ImageRun({ data: logo.data, type: logo.type, transformation: { width: logo.width, height: logo.height } })]
            })] : [new d.Paragraph('')];
            return new d.TableCell({
                borders: noBorders, verticalAlign: d.VerticalAlign.CENTER,
                width: { size: width, type: d.WidthType.DXA }, children: kids
            });
        }

        // Header: school logo | school name | org logo
        var header = new d.Table({
            width: { size: 9360, type: d.WidthType.DXA },
            columnWidths: [2000, 5360, 2000],
            borders: { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder, insideHorizontal: noBorder, insideVertical: noBorder },
            rows: [new d.TableRow({
                children: [
                    logoCell(schoolLogo, d.AlignmentType.LEFT, 2000),
                    new d.TableCell({
                        borders: noBorders, verticalAlign: d.VerticalAlign.CENTER,
                        width: { size: 5360, type: d.WidthType.DXA },
                        children: [
                            para([run('Bacolod City College', { bold: true, size: 28 })], { align: d.AlignmentType.CENTER, after: 40 }),
                            para([run('Office of Student Affairs', { size: 22 })], { align: d.AlignmentType.CENTER, after: 40 }),
                            para([run(req.org_name || '', { bold: true, size: 22 })], { align: d.AlignmentType.CENTER, after: 0 })
                        ]
                    }),
                    logoCell(orgLogo, d.AlignmentType.RIGHT, 2000)
                ]
            })]
        });

        var children = [
            header,
            para([run('')], { after: 200 }),
            para([run(L.date)], { after: 0 }),
            para([run('Control No: ' + L.control)]),
            para([run(L.salutation, { bold: true })])
        ];
        L.body.forEach(function (p) {
            children.push(para([run(p)], { align: d.AlignmentType.JUSTIFIED, indent: true }));
        });
        children.push(
            para([run(L.closing)], { after: 400 }),
            para([run('Respectfully yours,')], { after: 500 }),
            para([run(L.president, { bold: true })], { after: 0 }),
            para([run('President, ' + (req.org_name || ''))], { after: 300 }),
            para([run('Noted by:')], { after: 500 }),
            para([run(L.adviser, { bold: true })], { after: 0 }),
            para([run('Adviser')], { after: 300 }),
            para([run('Approved by:')], { after: 500 }),
            para([run(L.approver, { bold: true })], { after: 0 })
        );

        var doc = new d.Document({
            sections: [{
                properties: { page: {
                    size: { width: 12240, height: 15840 },
                    margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
                } },
                children: children
            }]
        });

        var blob = await d.Packer.toBlob(doc);
        var a = document.createElement('a');
        var safeName = String(req.control_no || 'letter').replace(/[^\w\-]+/g, '_');
        a.href = URL.createObjectURL(blob);
        a.download = 'Solicitation_Letter_' + safeName + '.docx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    } catch (err) {
        console.error(err);
        alert('Could not create the DOCX: ' + err.message);
    }
}


/* ---------- Notification bell (organization pages only) ---------- */
(function () {
    // Org pages live in /org/ ; the bell is added to the top bar, no changes to header.php needed.
    if (location.pathname.indexOf('/org/') === -1) { return; }

    function initBell() {
        var bar = document.querySelector('.top-navbar');
        if (!bar || document.getElementById('notifBell')) { return; }

        var wrap = document.createElement('div');
        wrap.id = 'notifBell';
        wrap.style.cssText = 'position:relative; margin-left:auto; margin-right:14px; order:2;';
        wrap.innerHTML =
            '<button type="button" id="notifBellBtn" aria-label="Notifications" style="position:relative; background:#fff; border:1px solid var(--border-color); border-radius:50%; width:40px; height:40px; cursor:pointer; color:var(--forest-green); font-size:1.05rem;">' +
            '<i class="fa-solid fa-bell"></i>' +
            '<span id="notifBadge" style="display:none; position:absolute; top:-5px; right:-5px; min-width:18px; height:18px; padding:0 5px; border-radius:9px; background:#e74c3c; color:#fff; font-size:.68rem; font-weight:700; line-height:18px; text-align:center;">0</span>' +
            '</button>' +
            '<div id="notifPanel" style="display:none; position:absolute; right:0; top:48px; width:340px; max-width:92vw; background:#fff; border:1px solid var(--border-color); border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,.18); z-index:1100; overflow:hidden;"></div>';

        var clock = bar.querySelector('.datetime-display');
        if (clock) { bar.insertBefore(wrap, clock); } else { bar.appendChild(wrap); }

        var btn = wrap.querySelector('#notifBellBtn');
        var panel = wrap.querySelector('#notifPanel');
        var badge = wrap.querySelector('#notifBadge');

        function render(data) {
            var n = data.unread || 0;
            badge.textContent = n > 99 ? '99+' : n;
            badge.style.display = n > 0 ? 'inline-block' : 'none';

            var html = '<div style="padding:12px 16px; border-bottom:1px solid var(--border-color); font-weight:700; color:var(--forest-green);">' +
                       '<i class="fa-solid fa-bell"></i> Notifications' + (n ? ' (' + n + ' new)' : '') + '</div>';
            if (!data.items || !data.items.length) {
                html += '<div style="padding:26px 16px; text-align:center; color:var(--text-muted); font-size:.88rem;">No notifications yet.</div>';
            } else {
                html += '<div style="max-height:340px; overflow-y:auto;">';
                data.items.forEach(function (it) {
                    var ok = it.type === 'approved';
                    html += '<a href="notifications.php" style="display:block; padding:11px 16px; border-bottom:1px solid var(--border-color); text-decoration:none; color:#222; border-left:4px solid ' + (ok ? '#79B425' : '#e74c3c') + '; background:' + (it.is_read ? '#fff' : '#f4fbf3') + ';">' +
                            '<div style="font-weight:700; font-size:.85rem; color:var(--forest-green);">' + esc(it.title) + (it.is_read ? '' : ' <span style="color:#e74c3c; font-size:.7rem;">NEW</span>') + '</div>' +
                            '<div style="font-size:.8rem; color:#444; margin-top:2px;">' + esc(it.message.length > 110 ? it.message.substring(0, 110) + '…' : it.message) + '</div>' +
                            '<div style="font-size:.72rem; color:var(--text-muted); margin-top:3px;">' + esc(it.time) + '</div></a>';
                });
                html += '</div>';
            }
            html += '<a href="notifications.php" style="display:block; padding:11px 16px; text-align:center; font-weight:700; font-size:.85rem; color:var(--royal-green); text-decoration:none;">View all notifications</a>';
            panel.innerHTML = html;
        }

        function load() {
            fetch('notifications_api.php', { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { if (d) { render(d); } })
                .catch(function () { /* bell stays empty */ });
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) { panel.style.display = 'none'; }
        });

        render({ unread: 0, items: [] });
        load();
        setInterval(load, 60000);   // refresh every minute
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initBell); } else { initBell(); }
})();