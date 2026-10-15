# User reports — a user → admin channel, with an optional forward

Status: **complete (2026-10-15): R1–R7 done, core v2.14.0–v2.20.0** plus the
installation package for the gesca84 forward. Open items in §8.
Decisions marked *(agreed)* were taken with the maintainer while studying
the feature; *(proposed)* ones need approval before the phase that uses them.

## 1. Purpose and scope

Logged-in users (and, by setting, guests) report a problem from the page
where they see it; the report reaches the **admin** of the installation in
the panel. An installation may add a **forwarder** that lets an admin send a
report on to an external helpdesk, under the admin's responsibility.

The first consumer is staging: testers (the maintainer and the client's
staff) report what they find while they test, from the panel and from the
storefront, and the admin forwards the technical ones to the helpdesk the
installation uses. The feature is generic: an installation with no
forwarder still gets a useful inbox.

Out of scope: a conversation thread (admin replies go by email as today),
ticket states beyond read/forwarded, SLA, assignment.

## 2. Decisions

1. *(agreed)* **Customers never reach an external helpdesk directly.** The
   core channel is user → admin; only an admin forwards.
2. *(agreed)* **Reports are `messages` rows.** The contact form is already a
   user → admin channel with an admin list (`MessageResource`) and an
   account page (`/messaggi`); one inbox, one history for the user.
3. *(agreed)* **Access setting** `mercatura.reports.access`:
   `off` | `auth` (default) | `public`.
4. *(agreed)* **Guests never attach files**, whatever the setting.
5. *(agreed)* **The forward is pseudonymised.** The external ticket carries
   the forwarding admin as reporter and a reference to the message
   (`messages#123`), never the user's name, email, user id or IP. The admin
   reviews the outgoing text and attachments before sending.
6. *(agreed)* **Screen capture with double consent**: an explicit checkbox
   (default off, purpose stated) enables the capture button; the browser asks
   its own permission on click; the user sees a preview before sending.
7. *(agreed)* **Antivirus scanning is a future item** (§8), behind a contract.
8. *(agreed)* **Account messages are matched by `user_id`.** Today `/messaggi`
   matches by email and registration does not verify email, so an account
   created with somebody else's address sees that person's contact-form
   messages. From R1 the account pages list only rows linked to the user;
   contact messages sent while logged in get `user_id` too. Email
   verification stays a separate decision (§8).
9. *(agreed)* **The reporter decides the channel.** Customers and guests
   create `messages` rows (the inbox). **Admins never do**: an admin's report
   (panel button, or the storefront dialog while logged in as admin) goes
   straight to the forwarder; with no forwarder, or without
   `messages.forward`, the admin entry points are hidden. Retries and
   delivery state of those reports are the forwarder's business (the
   helpdesk client keeps its own outbox); the core keeps no copy.
10. *(proposed)* Data shaped like the forward target, so a forward maps fields
   one to one: `kind` uses the values `bug | request | question | feedback`,
   subject ≤ 200, message ≤ 5000, ≤ 5 attachments of ≤ 10 MB.

## 3. Data model (R1)

New migration on `messages` (the existing migration is not edited):

| column | type | notes |
|---|---|---|
| `type` | string(16), default `contact`, indexed | `contact` \| `report` |
| `user_id` | nullable FK users, null on delete | set whenever the sender is logged in |
| `reporter_role` | string(16), nullable | `customer` \| `guest`, decided server side (admins never write here, §2.9) |
| `kind` | string(16), nullable | reports only |
| `page_url` | string(2048), nullable | the page the report was sent from |
| `context` | json, nullable | area, route name, app version, viewport, user agent, recent JS errors |
| `screenshot_consent_at` | timestamp, nullable | proof of consent for a capture |
| `screenshot_consent_version` | string(16), nullable | version of the consent text shown |
| `forwarded_at` | timestamp, nullable | |
| `forwarded_by` | nullable FK users | |
| `forward_reference` | string(64), nullable | id returned by the forwarder |

Each column arrives with the phase that reads it: R1 adds `type` to
`context`, R3 the `forwarded_*` columns, R6 the `screenshot_consent_*` ones.
`context.app_version` is left out: the core has no version source of its own,
a forwarder adds the installation's.

`email`, `name`, `surname` stay as they are (for a logged-in reporter they are
copied from the account, not from the form). Report-only columns stay null
on contact rows; every column is read by the panel or the forward (no dead
columns). Enums: `App\Enums\MessageType`, `App\Enums\ReportKind`,
`App\Enums\ReporterRole`.

Attachments: media collection `attachments` on `Message`, on the **`local`
disk** (`storage/app`, never served by the web server). Original file names
are kept as media `name` only; stored names are generated.

## 4. Security

**Identity is server side.** Role, area, user and IP come from the session
and the request, never from form fields. Nobody can post as an admin.

**Rate limits** (named limiter `reports`, config values):
logged in 5/min and 20/day per user plus 50 MB/day uploaded; guests 3/min
and 10/day per IP. The admin panel form uses the per-user limits too.

**Captcha**: the existing `Captcha::rules('report')` on the storefront form
(reCAPTCHA v3 is invisible). `public` requires a real captcha provider:
with `MERCATURA_CAPTCHA_PROVIDER=null` the effective access falls back to
`auth` and the panel shows a notice. Guests also get the honeypot field.

**Text**: stored as plain text, always escaped; the panel never renders
user HTML. Lengths as in §2.10.

**Attachments** (logged-in users only):
1. allowed: PNG, JPEG, WebP, PDF; checked by content (`mimetypes`) and
   extension; no SVG;
2. images are **re-encoded** before storage (drops EXIF/GPS, defeats
   polyglot files): `ImageSanitizer` gains PNG and WebP next to JPEG;
3. PDFs are stored as received and are never displayed inline;
4. download only through a panel route gated by `MessagePolicy::view`, with
   `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`;
5. the scan hook of §8 plugs in between validation and storage.

**Retention** (R7): attachments and captures are deleted after
`mercatura.reports.attachment_retention_days` (default 90) by a scheduled
command; the text stays.

## 5. Screen capture (R6)

Screen Capture API (`navigator.mediaDevices.getDisplayMedia`): one frame is
drawn to a canvas and turned into a PNG, then every track is stopped. No new
dependency. Requested with `preferCurrentTab: true` and
`displaySurface: 'browser'` (honoured by Chromium, ignored elsewhere). The
report dialog is hidden for the frame and shown again.

Flow: checkbox (off) with the purpose text → enables "Cattura schermata" →
browser permission → preview with "Scarta" / "Rifai" → the capture is one of
the ≤ 5 attachments and goes through the same pipeline. Unchecking the box
discards it. The consent time and text version are stored (§3).

Limits, stated in the UI where relevant:
- **not available on mobile browsers** (iOS Safari, Android Chrome): the
  option is not shown; users attach the phone's own screenshot;
- the browser picker can still offer the whole screen: the preview is the
  safeguard;
- needs a secure context (HTTPS, or `localhost`).

The capture code is a separate Vite chunk loaded when the dialog opens: no
weight on first paint, no CLS (the dialog overlays).

## 6. Forwarding (R3)

Core: contract `App\Contracts\MessageForwarder`
(`isAvailable(): bool`, `label(): string`,
`forward(ForwardedReport $report): ForwardResult`), default
`NullMessageForwarder` (unavailable), selected by
`mercatura.providers.message_forwarder`. Installation packages may bind
their own implementation. New permission `messages.forward`.

Panel action "Inoltra", visible when the forwarder is available, the user
has the permission and the row is a report not yet forwarded. The modal
shows what will leave:
- title and description **prefilled with automatic redaction** (emails,
  phone numbers, IBAN, card-like numbers) and editable;
- attachments listed **unchecked**, with a warning that captures of account
  pages usually show personal data;
- the pseudonymous reference and technical context, read-only.

`ForwardedReport` (DTO): kind, title, description, page_url (query secrets
stripped), context (`reporter_role`, area, route, app version, viewport,
user agent, JS errors, `reference` = `messages#<id>`), the forwarding
admin's name and email as reporter, chosen attachment paths. The message
itself is never changed; `forwarded_at/by/reference` record the forward.

The helpdesk ticket shows only the description, the page URL, the reporter's
phone and the user agent (`context.browser.user_agent`); nothing else of
`context`. The core therefore also gives the DTO a plain-text **technical
block** (reference, reporter role, area, route, viewport, app version, recent
JS errors), which a forwarder whose target ignores structured context appends
to the description.

Installation side (not in this repository): a private package
(e.g. `nereauweb/mercatura-support`) implementing `MessageForwarder` on top of
the installation's helpdesk client, required by the installation only.

## 7. Phases

Each phase ends with pint, larastan and the full suite green, a tag, and the
stop criterion met.

**R1 — Data and submission (core, no UI).**
Migration, enums, model casts and media collection, `ImageSanitizer` for
PNG/WebP, action `App\Actions\Messages\SubmitReport` (validation, identity,
context, attachments, limits), config `mercatura.reports`, limiter, account
pages by `user_id` (§2.8), contact form sets `user_id`/`reporter_role`.
*Stop:* feature tests cover roles, access modes and the captcha fallback,
guest attachment refusal, MIME spoofing (a PHP file renamed `.png`),
re-encoding (EXIF gone), limits, and the account pages matching by `user_id`.

*Done 2026-10-15 (v2.14.0).* `App\Actions\Messages\SubmitReport`,
`App\Support\Reports\ReportAccess`, enums `MessageType`, `ReportKind`,
`ReporterRole`, disk `reports` (`storage/app/reports`), `Message::ownedBy()`.
Deviations: the captcha fallback asks the provider (`enabled()`), so
reCAPTCHA without keys also counts as missing; limits are enforced inside the
action (any entry point gets them), not by a route limiter; the retention
setting arrives with R7; messages sent before R1 have no `user_id` and stay
visible to the admin only (no backfill by email, which would reopen §2.8).

**R2 — Admin panel.**
`MessageResource`: type filter and badge, report fields, attachments with the
protected download route, read state. Lang keys in `lang/it/admin.php`.
*Stop:* a report created through `SubmitReport` shows in the list with its
attachments; a customer cannot download an attachment (403).

*Done 2026-10-15 (v2.15.0).* Type/kind columns and filters, attachment
count, "Dettagli tecnici" section (page, area, route, viewport, browser, JS
errors, download links), `Admin\MessageAttachmentController` at
`admin.messages.attachment` (login, `MessagePolicy::view`, attachment
disposition, `nosniff`, CSP `sandbox`, `no-store`), list subheading when
`public` runs as `auth`. Found on the way: the `User::customer` accessor
creates an empty customer row when none exists; `SubmitReport` reads the
relation instead.

**R3 — Forward contract and action.**
§6 in the core with the null driver and a fake in tests, plus the admin's
own report (§2.9): topbar action "Segnala un problema" (render hook), same
fields, page URL of the current panel page, text redaction as in §6, all
attachments sent, a warning that panel captures may show customer data; it
calls the forwarder directly and stores nothing.
*Stop:* both actions are hidden with the null driver and with no permission;
with the fake, a forwarded message carries no customer name/email/id/IP and
only the checked attachments; a forwarded row cannot be forwarded twice; an
admin report creates no `messages` row.
Then, outside the core: the installation package and its activation on
staging, verified end to end with the helpdesk.

*Core done 2026-10-15 (v2.16.0).* `App\Contracts\MessageForwarder`,
`App\Drivers\Messages\NullMessageForwarder`, `ForwardedReport` (DTO),
`TechnicalBlock`, `Redactor`, actions `ForwardMessage` and `SendAdminReport`,
"Inoltra" on `ViewMessage`, `App\Http\Livewire\Admin\ReportProblem` in the
top bar (`USER_MENU_BEFORE`), permission `messages.forward` (created by the
migration as well, so existing installations get it on deploy). Selection:
`MERCATURA_MESSAGE_FORWARDER=<name>` resolves the container binding
`mercatura.message-forwarders.<name>`; an unknown name logs a warning and
behaves as null (a missing package must not break the panel). The shared
pieces of R1 moved to `ReportLimits`, `ReportAttachments`, `ReportContext`.
The page URL leaves without its query string.

Installation side done the same day: `nereauweb/mercatura-support` v0.1.0
(private) binds `mercatura.message-forwarders.support` over support-client
and appends the technical block to the description; its tests run inside
the installation. On gesca84 staging (`MERCATURA_MESSAGE_FORWARDER=support`)
an admin report reached the helpdesk end to end on 15/10 18:48
(ticket `01m507ekagtxw3gjfptfnc98bf`).

At this point testers on staging can report from the panel and the admin
can forward: the first usable milestone.

**R4 — Account area.**
Storefront component `frontend.components.report.dialog` (Alpine dialog,
lazy chunk) and a "Segnala un problema" tile on `frontend.customer.index`;
the user's reports appear in `/messaggi`. Copy in `lang/it/frontend.php`.
*Stop:* a customer sends a report with attachments from the account area;
storefront integrity tests pass; Lighthouse on the account page unchanged.

*Done 2026-10-15 (v2.17.0).* `frontend.components.report.dialog` (Alpine
`reportDialog`, overlay, JSON post to `frontend.reports.store` →
`FrontendReportController`, which leaves every decision to SubmitReport:
403 refused, 422 invalid, 429 limits) and `frontend.components.report.trigger`
(renders nothing for who may not report); "Segnala un problema" and
"Messaggi" tiles on `frontend.customer.index` (the account had no link to
`/messaggi`), a type column in the account's message list, icon `alert`.
Deviation: the dialog's script is in the main bundle (~0.4 KB gzip) instead
of a lazy chunk: a chunk would delay Alpine's start on every page that shows
the trigger, as the product-page chunk does. Checked in Chrome (puppeteer,
390×844): CLS 0, dialog hidden until opened, no console errors, report with
attachment stored with area `account`. Recent JS errors are collected from
the moment the bundle runs.

**R5 — Public storefront.**
Entry point in the layout (a discreet link, via an existing `@stack` point,
no layout shift), shown according to the effective access mode; guests get
the form without attachments, with captcha and honeypot.
*Stop:* `auth` shows it only when logged in; `public` with null captcha
behaves as `auth`; CLS 0 and LCP unchanged on home, listing and product.

*Done 2026-10-15 (v2.18.0).* The layout mounts the dialog once (when the
request has a session); the entry point is a "Segnala un problema" link in
the footer's legal bar, below the fold. `ReportAccess::offers()` decides who
sees it: whoever `allows()` (customers, guests in `public` mode) plus admins
for whom `forwardsDirectly()` holds (forwarder available and
`messages.forward`); an admin's storefront report goes to `SendAdminReport`
(intro and hint for admins, attachments allowed, no captcha). Guests get a
confirmation that does not point to an account. Measured in Chrome (390×844,
CPU ×4) on home, listing and product, guest vs logged-in customer: CLS 0
everywhere, LCP within noise (±12 ms).

**R6 — Screen capture.**
§5 in both the storefront dialog and the panel action.
*Stop:* tested on Chromium and Firefox desktop (manual, on staging); hidden
where the API is missing; consent fields stored; capture re-encoded.

*Done 2026-10-15 (v2.19.0).* Shared module `resources/js/report-capture.js`
(`captureScreen`, `addToFilePond`): imported by the storefront dialog,
loaded alone in the panel (Vite entry, `BODY_END` hook only when the admin's
report is available). Storefront: consent box (text in
`frontend.reports.capture.consent`, version in
`mercatura.reports.screenshot_consent_version`, stored with the report only
when a file comes with it), "Cattura schermata" enabled by the box, preview
with retake/discard; admins on the storefront get a warning instead of the
consent. Panel: a "Cattura schermata" button in the admin's report modal
adds the frame to the FileUpload (FilePond), where the admin sees and can
remove it; the Filament modals are hidden for the frame. Checked in Chrome
(tab capture auto-accepted): button disabled until consent, preview shown,
report stored with consent and a PNG of the page without the dialog.

*Fix 2026-10-15 (v2.20.1), after testing on staging:* opened from the footer
(R5) the dialog sits over the bottom of the page and the page does not scroll
under a modal, so the capture always showed the footer. "Cattura schermata"
now enters a capture mode (`pickAndCapture`): the dialog (or the Filament
modal) hides, the scroll lock is lifted, a bar fixed at the bottom asks to
scroll to the problem and press "Cattura", whose click starts the browser
prompt; then the dialog comes back with the preview and the lock is
restored. Escape or "Annulla" return to the dialog unchanged. The storefront
loads the capture module on first use (dynamic import), not with the bundle.

*Fix 2026-10-16 (v2.20.2), after testing on staging:* the admin saw only file
names, so a capture had to be downloaded to be looked at, also when choosing
what to forward. `admin.messages.attachment.preview` shows an attachment
inline when it is a re-encoded image (PNG, JPEG, WebP; PDFs 404), behind the
same login and `MessagePolicy::view`, with `nosniff` and a sandbox CSP.
`AttachmentPreview::html()` renders a thumbnail (opening the full image in a
new tab) and the download link: in the report's details and as the labels of
the forward's attachment checkboxes.

**R7 — Retention and documentation.**
Scheduled pruning of attachments (§4), installation docs (settings, consent
text to review, privacy notice), demo seeder rows for the panel.
*Stop:* pruning tested with a frozen clock; docs reviewed.

*Done 2026-10-15 (v2.20.0).* `cleanup:report-attachments` (`--days`,
`--dry-run`; `mercatura.reports.attachment_retention_days`, default 90,
`0` keeps everything) clears the `attachments` collection of reports sent
before the cutoff, keeping the text; scheduled daily at 04:20. README:
the three settings and a "User reports" section with the go-live duties
(review the consent and notice texts, bump the consent version, mention
reports in the privacy policy). DemoSeeder adds a contact message and two
reports to the panel's inbox. Not done: deleting attachments "when the
message is closed" (§4): messages have no closed state.

## 8. Future items (not planned)

- **Antivirus scan** of uploads behind a contract (`AttachmentScanner`, null
  default, ClamAV driver), run before storage; needs a daemon on the host.
- **Redaction in the capture preview** (black boxes drawn by the user before
  sending).
- **Email verification** for new accounts (`MustVerifyEmail`): closes the
  wider "registered with someone else's address" case beyond messages;
  changes the registration flow, so it is a decision of its own.
- Admin replies from the panel (a thread on the message).
- ~~Order files on the public disk~~ — *done 2026-10-16 (v2.21.0)*: order
  attachments on the private `order-files` disk, customization artwork on
  `local`, served only by `OrderFileController` (customer: own orders; panel:
  `OrderPolicy::view`), existing files moved by a migration
  (`OrderFilesToPrivateDisk`).

## 9. Open questions

1. *(answered 2026-10-15)* The helpdesk accepts any reporter the application
   declares (`reporter.email` required; `name`, `user_ref`, `phone`
   optional; other keys dropped) and matches it against the client's
   contacts: the forwarding admins should be added as contacts there. An
   `on_behalf_of` field was offered and **declined**: the forward is
   pseudonymised on purpose (§2.5). `context` is **not** shown in a human
   ticket (only description, page URL, phone, user agent), so the reference
   and the technical data travel in the description (§6); `user_ref`, if
   sent, is the admin's id, never the user's.
2. *(agreed)* Consent text and privacy notice: a starting text is written in
   the lang keys (R6, R7); the client reviews it.
3. *(agreed)* Limits and retention defaults (§4): 5/min, 20/day, 50 MB/day,
   90 days.
4. *(answered)* Admins reporting: straight to the forwarder, never stored
   as messages (§2.9).
