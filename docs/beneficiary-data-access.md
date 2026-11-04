# Beneficiary data — who sees what

Week 9 review (NFR-11, SRS §3.1). Checked by `tests/Feature/Beneficiaries/DataVisibilityTest.php`
and `tests/Feature/Access/ScreenAccessMatrixTest.php` — if code and this table disagree, those tests fail.

## Roles

| Role | Beneficiaries | Applications | Visits | Decide (approve / reject / Zakat) | Archive |
|---|---|---|---|---|---|
| Super Admin (founder) | all · edit | all · edit | all · record | yes | yes |
| Manager | all · register / edit / assign officer | all · create / edit / send for verification | all · record | no | no |
| Field Officer | **own cases only** · register / edit | **own only** · create / edit / send for verification | **own only** · record | no | no |
| Finance | all · view | all · view | all · view | no | no |
| Trustee | all · view | all · view | all · view | no | no |
| Auditor | all · view | all · view | all · view | no | no |
| Campaign Manager | — | — | — | no | no |
| Donor (website / app) | — | — | — | — | — |

**A field officer's own case** is a household assigned to them, or registered by them while
nobody is assigned. When a manager moves a case to another officer, the first officer loses it.
They also see applications assigned to them, and visits they made.

Deciding is `beneficiaries.approve` in `config/permissions.php`, which only the Super Admin has.
To let Managers decide too, change Manager's `beneficiaries` level from `C` to `F` and re-run the
seeder (`F` also allows archiving).

## Protections

- **CNIC** — encrypted at rest, unique by HMAC hash, never sent to the browser or the audit log,
  shown only masked (`35202-•••••••-3`). Search by full CNIC matches the hash.
- **Documents and visit photos** — private `local` disk; no public URL. Downloaded only through
  the "Download document" / "Visit photos" buttons by staff who may view the record; every
  download is in the activity log.
- **Scope everywhere** — lists, search boxes, global search, record URLs (404), relation lists and
  policies all use the same rule (`Beneficiary::visibleTo`, `Application::visibleTo`,
  `VerificationVisit::visibleTo`).
- **No API** — the mobile/website API has no beneficiary, application or visit endpoint.
- **Nothing is deleted** — beneficiaries are archived; applications and visits are never deleted;
  visits are never edited (a correction is a new visit).
- **Every step is audited** — submit, verification, visit, approve, reject, re-open, Zakat decision,
  household changes from a visit, file downloads.
