# SRS-TOC-01 addendum A1: buyer database (TOC-BUY)

Approved by the client on 8 Oct 2026 (items 1 to 4 of the "extract more from enquiry emails" proposal).
Not approved, so not built: storing the buyer's free-text message. TOC-LOG-003 still applies in full:
only extracted fields are stored, never message bodies.

Source today: all imported messages come from Japanese Car Trade (`inquiry@japanesecartrade.com`). The emails
use a fixed labelled layout ("Name : …", "Country : …", "Make : …"). A 200-message sample showed name, email,
phone field, make and model in 100 % of messages, country 99.5 %, year 89 %, port 85 %, drive 75 %,
buyer type 52 %, budget 12 %.

| ID | Requirement | Acceptance criterion |
|---|---|---|
| TOC-BUY-001 | For senders with "Collect buyer details" on, the importer reads labelled lines into buyer and vehicle fields (name, country, port, phone, buyer type, make, model, year, drive, budget, enquiry kind, other spec fields, portal and TOCO stock references). Lines inside the buyer's own message are ignored. | "Budget : 999999" typed inside the message is not saved as the budget. |
| TOC-BUY-002 | Values are normalised: titles removed from names, country to ISO code, phone to E.164 (repairing doubled country codes and trunk zeros), buyer type to individual/dealer, years 1950 to next year. "Not Shared" phones are no phone. | "+254+0712345678" with country Kenya becomes +254712345678. |
| TOC-BUY-003 | One buyer per email address and one enquiry per message are stored in `mailer_buyers` and `mailer_buyer_enquiries`. | Two enquiries from the same address give one buyer with an enquiry count of 2. |
| TOC-BUY-004 | A newer message updates the buyer's details; an older one only fills empty details. Recording the same message twice changes nothing. | Reading an older message after a newer one keeps the newer port. |
| TOC-BUY-005 | Brevo gets FIRSTNAME, LASTNAME, COUNTRY and PHONE from the buyer details (still only when empty in Brevo, rule 6) and, once `mailer:brevo:setup` has created them, PORT, BUYER_TYPE, COUNTRY_CODE, LAST_MAKE, LAST_MODEL, LAST_YEAR, ENQUIRY_COUNT. | A contact with FIRSTNAME already set in Brevo keeps it; PORT is sent. |
| TOC-BUY-006 | `mailer:buyers:backfill` (or "Read older enquiries" on the Buyers screen) re-reads already-imported messages read-only and records their buyers, resuming where it stopped; `--brevo` then fills empty Brevo details on existing contacts only, without changing lists, SOURCE or dates, skipping unsubscribed and bounced contacts. | Running the backfill twice reads each message once. |
| TOC-BUY-007 | Mailer > Buyers > All buyers lists buyers with search and filters (country, type, make asked about, enquiry kind, last enquiry period, repeat buyers, valid phone), a profile page with all enquiries and a WhatsApp link, and a CSV download of the filtered list. | Filtering by Kenya and "Toyota" lists only Kenyan buyers who asked about Toyota. |
| TOC-BUY-008 | Mailer > Buyers > Demand shows the most asked-for vehicles, makes, countries, enquiries per month, enquiry kinds, buyer types and steering, by period and country. | The 90-day view counts only enquiries from the last 90 days. |
| TOC-BUY-009 | Mailer > Buyers > Stock matching lists buyers who asked for a vehicle like a chosen stock vehicle (or a typed make, model, year range, steering, country, period), with a CSV download. Vehicles are only read. | Picking a 2016 Hiace lists buyers who asked for a Hiace from 2014 to 2018. |
| TOC-BUY-010 | Buyers with no enquiry for 24 months (`mailer.buyer_retention_months`) are deleted with their enquiries by the daily `mailer:cleanup`. | A buyer last seen 25 months ago is gone after the cleanup. |

CSV downloads neutralise cells starting with `=`, `+`, `-` or `@` (values come from emails), except phone numbers.
