# Dead Body Mapping System v1.6 — Staff-Filed Reports & Animal Carcass Assessment

Mobile-first PHP + MariaDB/MySQL public-safety reporting, mapping and case-response tracking system for deployment under `https://www.labhexa.com/db`.

## v1.6 changes

- Filing a report now requires an Admin or Operator account — the public site no longer has a self-service report form. Staff log in at `/admin/login.php` with their existing username/password (no separate reporter accounts) and file reports at `/report`. Each report records which staff account filed it.
- The public homepage now shows only aggregate case counts (Pending / In progress / Rescued) — no report form or case-tracking form.
- Animal reports now collect a detailed carcass assessment: date/time observed, weather, species, estimated size, carcass count, decomposition state, distance from water source and human settlement, proposed disposal method, equipment needed and disinfection materials needed. Also visible/editable in the admin case view and included in exports and printed case reports.
- See `UPGRADE_v1.6.txt` — this release requires a database migration.

## v1.5 mobile usability improvements

- Phone-first layout across public and admin pages
- 48–52px minimum touch targets for important controls
- iPhone safe-area support and `viewport-fit=cover`
- 16px+ form fields to prevent unwanted iOS input zoom
- Fixed mobile bottom navigation for Public and Admin interfaces
- Admin dashboard uses readable case cards on phones instead of a wide table
- Every mobile case card shows current status and **Next Required Action**
- Full-width **Update Case** action on phone
- One-tap Call Reporter / Police Contact / Response Team when phone numbers exist
- Guided case workflow remains above all detailed case information
- Advanced Edit is collapsed by default to reduce field overload
- Report form has four clear mobile steps: Type → Location → Photos → Contact
- Sticky mobile Submit Report action
- Rear-camera image selection plus instant selected-photo previews
- Offline status warning before a report/update is submitted
- Public home page now has direct Case ID tracking

## Accessibility

- Skip-to-main-content links
- Semantic navigation and improved labels
- Keyboard-visible focus rings
- Screen-reader live announcements for GPS, form errors, photo selection and connectivity
- Persistent accessibility control with Normal / Large / Extra Large text
- Persistent high-contrast mode
- Persistent reduced-motion mode
- Honors operating-system `prefers-reduced-motion`, `prefers-contrast`, and forced-colors settings
- Larger checkboxes and touch controls
- Accessible GPS permission dialog with keyboard focus management
- Form fields use mobile input modes, autocomplete and enter-key hints where appropriate

## Public reporting

- Human / Animal / Unsure
- Filed by logged-in Admin/Operator staff only (no public self-service form)
- One-tap exact GPS permission flow
- High-accuracy location with accuracy capture
- Draggable map fallback
- Multiple restricted evidence photos
- Reporter privacy
- Human-case police notification support
- Animal Carcass Assessment fields (species, decomposition state, disposal planning, etc.)
- Unique public Case ID and public progress tracking

A website cannot bypass a browser/OS location denial. If permission is denied, the staff member filing the report can re-enable it or select the exact location manually on the map.

## Guided response workflow

### Human
`Confirm → Inform Nepal Police → Dispatch Team → Recover → Police/Hospital/Mortuary Handover → Close`

### Animal
`Confirm → Dispatch Local Volunteer / Municipality / Animal Team → Recover → Bury/Dispose → Close`

### Unsure
`Confirm → Dispatch Assessment Team → Found Human OR Found Animal → Continue correct workflow`

### Fake report
`Verify false report → record reason → Mark Fake & Close`

False/invalid/duplicate cases are retained with audit history rather than deleted.

## Secure exact-location sharing

Admins/operators can create expiring, revocable exact-GPS links for Nepal Police, rescue/recovery teams or other authorized recipients. Public human-case pages continue to expose only reduced-precision location data.

## Upgrade from v1.5

A database migration IS required for v1.6 — see `UPGRADE_v1.6.txt` for the full procedure (run `migrate_v1_6.php` or import `migrations/v1.6_animal_carcass_fields.sql`, then delete `migrate_v1_6.php`). This release also changes behavior: filing a report now requires an Admin or Operator login — the public report form is gone.

## Upgrade from v1.4 to v1.5

No database migration was required for v1.5. Upload the v1.5 patch into the existing `/public_html/db/` directory and overwrite the matching files. Keep the live `config.php` unchanged.

After upload, hard-refresh once (`Ctrl + F5`) or reopen the site. Asset URLs are versioned using file modification time, so normal users should receive the updated CSS/JavaScript automatically.

## Fresh installation

1. Create MySQL/MariaDB database and database user.
2. Import `database.sql`.
3. Copy `config.example.php` to `config.php` and enter deployment settings.
4. Create the first admin through `/setup/create_admin.php?key=...`.
5. Delete `/setup/` after admin creation.
6. Use HTTPS.

See `DEPLOYMENT.md` for the full step-by-step server deployment guide, including requirements, a post-deploy verification checklist, upgrade steps, and a security checklist.

## Production note

This remains an operational prototype. Formal government/public-authority deployment should include security, privacy/legal, incident-response, backup/restore, load, abuse-prevention and agency-integration reviews.
