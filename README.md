# BCC USOGSTS – Solicitation Tracking System (cleaned)

1. Put this folder in `htdocs/usogsts` (XAMPP).
2. Create DB `sol_system_db` (import `schema.sql` if you don't have it yet) and create the first admin user (see bottom of schema.sql).
3. Open http://localhost/usogsts/login.php

admin/  -> index, pending, approved, denied, funds, organizations, statistics
org/    -> index, profile, create_request, pending, approved, funds, statistics
