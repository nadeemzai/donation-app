# Go-live — Week 12 Day 5

| File | For |
| --- | --- |
| `Imdaad-Go-Live-and-Hypercare-Plan.docx` | Super Admin + developer: checklist before go-live, the go-live day, what to do if something goes wrong, the first four weeks |
| `Imdaad-Quick-Reference-Guides.docx` | Every staff member: print your role's page |
| `donor-import-template.csv` | The format for importing existing donors |

## Importing existing donors

1. Export the old donor list from Excel as **CSV UTF-8**, with a first row of headers like the template:
   `Name, Phone, Email, CNIC, NTN, Type, City, WhatsApp, Language` (order doesn't matter; other columns are ignored).
   - Name and a phone **or** e-mail are required. Phones like `3001234567` (Excel dropped the 0) are fixed automatically.
   - `WhatsApp` = yes only for donors who agreed to receive messages on WhatsApp.
   - `Type` = corporate for companies; `Language` = ur for Urdu messages.
2. Copy it to the server (e.g. `scp donors.csv deploy@server:/home/deploy/`), never by e-mail or chat.
3. Dry run — checks every row, saves nothing:
   `php artisan donors:import /home/deploy/donors.csv`
4. Open the report it names (`storage/app/private/imports/…`), fix the errors in the file, repeat 3 until clean.
5. Import: `php artisan donors:import /home/deploy/donors.csv --commit`
6. Delete the CSV from the server and your laptop (`rm /home/deploy/donors.csv`) — it holds phone numbers and CNICs.

A donor already in the system (same phone, e-mail or CNIC) is skipped, never duplicated, so running the import again is safe. Past donations are not imported: they stay in the old records.
