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

function letterParagraphs(r, stu) {
    var amount = 'PHP ' + Number(r.target_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
    return {
        date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
        control: stu ? (stu.control_no || ((r.control_no || '') + '-S' + String(stu.id || '').padStart(3, '0'))) : (r.control_no || ''),
        requestControl: stu ? (r.control_no || '') : '',
        salutation: 'To Whom It May Concern:',
        letterNo: stu ? (stu.control_no || ((r.control_no || '') + '-S' + String(stu.id || '').padStart(3, '0'))) : '',
        solicitor: stu ? (stu.student_name || '') : '',
        body: (stu ? [
            'Greetings from ' + (r.org_name || '') + (r.acronym ? ' (' + r.acronym + ')' : '') + '!',
            'The organization is conducting "' + (r.event_name || '') + '" on or before ' + (r.date_needed || '') +
                '. Purpose: ' + (r.purpose || '') + '.',
            'This letter introduces ' + (stu.student_name || '') +
                (stu.student_id ? ' (Student ID: ' + stu.student_id + ')' : '') +
                (stu.year_section ? ', ' + stu.year_section : '') +
                ', a listed solicitor of the organization, who is authorized to request donations in cash or in kind on its behalf for this activity.' +
                (Number(stu.target_amount) > 0
                    ? ' The tentative amount to be raised by the student is PHP ' + Number(stu.target_amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) +
                      ', as part of the organization\'s overall tentative amount of ' + amount + '.'
                    : ''),
            'We respectfully ask for your kind support. ' +
                'This solicitation is authorized by the Office of Student Affairs (Request Control No. ' + (r.control_no || '') + ').',
            (r.admin_remarks ? 'Conditions: ' + r.admin_remarks : '')
        ] : [
            'Greetings from ' + (r.org_name || '') + (r.acronym ? ' (' + r.acronym + ')' : '') + '!',
            'The organization is conducting "' + (r.event_name || '') + '" on or before ' + (r.date_needed || '') +
                '. Purpose: ' + (r.purpose || '') + '. The organization aims to raise ' + amount + ' for this activity.',
            'In this regard, we respectfully ask for your kind support through a donation in cash or in kind. ' +
                'This solicitation is authorized by the Office of Student Affairs (Control No. ' + (r.control_no || '') + ').',
            (r.admin_remarks ? 'Conditions: ' + r.admin_remarks : '')
        ]).filter(Boolean),
        closing: 'Thank you very much for your generosity and support.',
        president: r.president || '', chairmanCourse: r.course_year_sec || '', adviser: r.adviser || '',
        approver: r.approved_by || 'Office of Student Affairs'
    };
}

function openSolicitationLetterModal(req, stu) {
    var L = letterParagraphs(req, stu);
    var m = document.getElementById('letterModal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'letterModal';
        m.className = 'modal-overlay';
        document.body.appendChild(m);
    }
    window._currentLetterReq = req;
    window._currentLetterStudent = stu || null;

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
        '  <p>' + L.date + '<br>Control No: ' + L.control + (L.requestControl ? '<br>Request Control No: ' + L.requestControl : '') + '</p>' +
        '  <p><strong>' + L.salutation + '</strong></p>' +
        L.body.map(function (p) { return '<p style="text-align:justify; text-indent:40px;">' + p + '</p>'; }).join('') +
        '  <p>' + L.closing + '</p><br>' +
        '  <p>Respectfully yours,<br><br><strong>' + L.president + '</strong><br>' + (L.chairmanCourse ? L.chairmanCourse + '<br>' : '') + 'Chairman, ' + (req.org_name || '') + '</p>' +
        '  <p>Noted by:<br><br><strong>' + L.adviser + '</strong><br>Adviser</p>' +
        (L.solicitor ? '  <p>Authorized solicitor:<br><br><strong>' + L.solicitor + '</strong><br>Student</p>' : '') +
        '  <p>Approved by:<br><br><strong>' + L.approver + '</strong></p>' +
        '</div>' +

        '</div></div>' +
        '<div class="modal-footer"><button type="button" class="btn btn-outline-primary" onclick="closeModal(\'letterModal\')">Close</button>' +
        '<button type="button" class="btn btn-primary" onclick="downloadSolicitationDocx(window._currentLetterReq, window._currentLetterStudent)"><i class="fa-solid fa-download"></i> Download DOCX</button></div>' +
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

async function downloadSolicitationDocx(req, stu) {
    if (!req) { alert('No letter selected.'); return; }
    try {
        var d = await loadDocxLib();
        // stu can be one student, a list of students (one page per student) or nothing (general letter)
        var list = Array.isArray(stu) ? stu : [stu];
        if (!list.length) { alert('There are no students listed yet.'); return; }
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

        function buildChildren(s) {
        var L = letterParagraphs(req, s);
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
            para([run('Control No: ' + L.control)], { after: L.requestControl ? 0 : 200 })
        ];
        if (L.requestControl) { children.push(para([run('Request Control No: ' + L.requestControl)])); }
        children.push(para([run(L.salutation, { bold: true })]));
        L.body.forEach(function (p) {
            children.push(para([run(p)], { align: d.AlignmentType.JUSTIFIED, indent: true }));
        });
        children.push(
            para([run(L.closing)], { after: 400 }),
            para([run('Respectfully yours,')], { after: 500 }),
            para([run(L.president, { bold: true })], { after: 0 }),
            para([run((L.chairmanCourse ? L.chairmanCourse + ' — ' : '') + 'Chairman, ' + (req.org_name || ''))], { after: 300 }),
            para([run('Noted by:')], { after: 500 }),
            para([run(L.adviser, { bold: true })], { after: 0 }),
            para([run('Adviser')], { after: 300 }),
            para([run('Approved by:')], { after: 500 }),
            para([run(L.approver, { bold: true })], { after: 0 })
        );
        if (L.solicitor) {
            children.push(
                para([run('')], { after: 200 }),
                para([run('Authorized solicitor:')], { after: 500 }),
                para([run(L.solicitor, { bold: true })], { after: 0 }),
                para([run('Student')], { after: 0 })
            );
        }
        return children;
        }

        // Each student gets a section of their own, so every letter starts on a new page
        var doc = new d.Document({
            sections: list.map(function (s) {
                return {
                    properties: { page: {
                        size: { width: 12240, height: 15840 },
                        margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
                    } },
                    children: buildChildren(s)
                };
            })
        });

        var blob = await d.Packer.toBlob(doc);
        var a = document.createElement('a');
        var one = !Array.isArray(stu) ? letterParagraphs(req, stu) : null;
        var safeName = String((one && one.letterNo) || req.control_no || 'letter').replace(/[^\w\-]+/g, '_');
        a.href = URL.createObjectURL(blob);
        a.download = Array.isArray(stu)
            ? 'Solicitation_Letters_' + safeName + '_all_students.docx'
            : 'Solicitation_Letter_' + safeName + '.docx';
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

    // Org profile popup (opened from the sidebar)
    function showOrgProfile(org) {
        var m = document.getElementById('orgProfileModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'orgProfileModal';
            m.className = 'modal-overlay';
            document.body.appendChild(m);
        }
        function row(label, value) {
            return '<tr><td style="padding:8px 0; color:var(--text-muted); width:38%; border-bottom:1px solid var(--border-color);">' + esc(label) +
                   '</td><td style="padding:8px 0; font-weight:600; border-bottom:1px solid var(--border-color);">' + esc(value || '—') + '</td></tr>';
        }
        var avatar = org.logo
            ? '<img src="../assets/uploads/org_logos/' + encodeURIComponent(org.logo) + '" alt="" style="width:72px; height:72px; border-radius:50%; object-fit:contain; background:#fff; border:3px solid var(--lemon-lime);">'
            : '<div style="width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,var(--royal-green),var(--lemon-lime)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem;">' + esc((org.acronym || '').substring(0, 4)) + '</div>';

        m.innerHTML =
            '<div class="modal-box">' +
            '<div class="modal-header"><h3><i class="fa-solid fa-id-card" style="color:var(--royal-green);"></i> Organization Profile</h3>' +
            '<button type="button" class="modal-close" onclick="closeModal(\'orgProfileModal\')">&times;</button></div>' +
            '<div class="modal-body">' +
            '<div style="display:flex; align-items:center; gap:16px; margin-bottom:18px;">' + avatar +
            '<div><h3 style="font-size:1.1rem; color:var(--forest-green); margin:0 0 4px;">' + esc(org.name) + '</h3>' +
            '<span class="badge badge-' + (org.status === 'active' ? 'active' : 'inactive') + '">' + esc(org.status) + '</span></div></div>' +
            '<table style="width:100%; border-collapse:collapse; font-size:.9rem;">' +
            row('Acronym', org.acronym) + row('Chairman', org.president) + row('Course, Year & Section', org.course) + row('Adviser', org.adviser) +
            row('Email', org.email) + row('Contact No.', org.contact) + row('Registered', org.since) +
            '</table></div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-outline-primary" onclick="closeModal(\'orgProfileModal\')">Close</button>' +
            '<a href="profile.php" class="btn btn-primary"><i class="fa-solid fa-pen-to-square"></i> Edit Profile &amp; Members</a></div>' +
            '</div>';
        openModal('orgProfileModal');
    }

    // Sidebar: show the organization (logo/acronym + clickable name) that opens the profile popup
    function applyOrgSidebar(org) {
        var box = document.querySelector('.sidebar-user');
        if (!org || !box || box.getAttribute('data-org-profile')) { return; }
        box.setAttribute('data-org-profile', '1');

        var nameEl = box.querySelector('.user-name');
        if (nameEl) {
            nameEl.textContent = org.name;
            nameEl.style.whiteSpace = 'normal';
            nameEl.style.textDecoration = 'underline';
            nameEl.style.textDecorationStyle = 'dotted';
        }

        var avatar = document.createElement('div');
        avatar.style.cssText = 'width:42px; height:42px; flex-shrink:0; border-radius:50%; overflow:hidden; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--royal-green),var(--lemon-lime)); color:#fff; font-weight:800; font-size:.7rem; border:2px solid var(--lemon-lime);';
        if (org.logo) {
            avatar.style.background = '#fff';
            avatar.innerHTML = '<img src="../assets/uploads/org_logos/' + encodeURIComponent(org.logo) + '" alt="" style="width:100%; height:100%; object-fit:contain;">';
        } else {
            avatar.textContent = (org.acronym || '').substring(0, 4);
        }

        var inner = document.createElement('div');
        inner.style.cssText = 'min-width:0; flex:1;';
        while (box.firstChild) { inner.appendChild(box.firstChild); }
        box.appendChild(avatar);
        box.appendChild(inner);

        box.style.cssText += 'display:flex; align-items:center; gap:10px; cursor:pointer;';
        box.title = 'Click to view organization profile';
        box.setAttribute('role', 'button');
        box.setAttribute('tabindex', '0');
        function open() { showOrgProfile(org); }
        box.addEventListener('click', open);
        box.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    }

    // Sidebar links for the org pages (added here so header.php does not need to change)
    function addOrgNav() {
        var nav = document.querySelector('.sidebar-nav');
        if (!nav || nav.getAttribute('data-org-nav')) { return; }
        nav.setAttribute('data-org-nav', '1');

        var items = [
            ['students.php', 'fa-user-graduate', 'Student Records'],
            ['returns.php', 'fa-camera', 'Return Records'],
            ['notifications.php', 'fa-bell', 'Notifications']
        ];
        var here = location.pathname.split('/').pop();
        var existing = {};
        var anchorLi = null;
        nav.querySelectorAll('a').forEach(function (a) {
            var h = (a.getAttribute('href') || '').split('/').pop();
            existing[h] = true;
            if (h === 'approved.php') { anchorLi = a.closest('li') || a; }
        });
        var parent = anchorLi ? anchorLi.parentNode : nav;
        var ref = anchorLi ? anchorLi.nextSibling : null;

        items.forEach(function (it) {
            if (existing[it[0]]) { return; }
            var a = document.createElement('a');
            a.href = it[0];
            a.innerHTML = '<i class="fa-solid ' + it[1] + '"></i> ' + it[2];
            if (it[0] === here || (it[0] === 'students.php' && here === 'student.php')) { a.className = 'active'; }
            if (it[0] === 'notifications.php') {
                a.innerHTML += '<span class="nav-badge" id="navNotifBadge" style="display:none;">0</span>';
            }
            var el = a;
            if (parent.tagName === 'UL' || parent.tagName === 'OL') { el = document.createElement('li'); el.appendChild(a); }
            parent.insertBefore(el, ref);
        });
    }

    function initOrgUi() {
        addOrgNav();
        initBell();
    }

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
            var nb = document.getElementById('navNotifBadge');
            if (nb) { nb.textContent = n > 99 ? '99+' : n; nb.style.display = n > 0 ? 'inline-block' : 'none'; }

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
                .then(function (d) { if (d) { render(d); applyOrgSidebar(d.org); } })
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

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initOrgUi); } else { initOrgUi(); }
})();