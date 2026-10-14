# TOCO CRM: operating guide

The TOCO CRM keeps every buyer enquiry in one place. Each enquiry has one owner, a reply deadline and a clear step from "Search & Order" to "Car Received". This guide covers daily work for staff (sections 1 to 12) and setup for admins (sections 13 to 18).

---

## 1. Getting started

**Your login.** Each staff member has their own login. Every action you take is recorded with your name. Do not share logins.

**Your role** decides what you see. Admins give roles on **Admin → System → Users**.

| Role | Who | Can do |
|---|---|---|
| CRM Admin | Owner, sales manager | Sees every Case, assigns and reassigns, changes settings, sees team results and reports |
| CRM Agent | Sales staff | Works the Cases they own; can take Cases from the Unassigned list |
| CRM Team lead | Senior sales | Agent rights, plus sees every Case of their queue, the team overview and reports |
| CRM Operations | Shipping and documents | Works the shipping and document steps (stages 03 and 04) on any Case |

**Where you start.** **CRM → Dashboard** is the CRM's front page. CRM staff who are not site admins land there straight after signing in.

**Set up your profile first** (**CRM → My profile**):

- **Nickname.** Buyers only ever see this name, in the email sender ("Ken · TOCO Japan"), the sign-off and "Your TOCO contact" on their purchase page. Your real name stays private. Without a nickname buyers see "TOCO Sales Team".
- **Show times in.** Choose your time zone, for example Asia/Colombo. Deadlines are always counted in Japan business hours, but you see them in your own time.
- **Emails.** "As they happen", or "Morning summary only". Late-reply alerts and new assignments always arrive at once.
- **Away dates.** While you are away you get no new Cases, and admins see your open Cases so they can hand them over.
- **I may take Cases from the unassigned queue.** Leave this on unless an admin says otherwise.

---

## 2. Words used in the CRM

| Word | Meaning |
|---|---|
| Case | One buyer enquiry or purchase, with a reference such as **C-2026-00042**. Everything about it is in one place. |
| Customer | A person or company, matched by email address. One customer can have several Cases. |
| Case type | **Vehicle sale** (follows the five TOCO steps), **Spare parts** or **General enquiry** (simple statuses). |
| Source | Where the Case came from: quote request, LC proforma, buy-now order, contact form, spare-part request, or phone, WhatsApp, email, walk-in or partner when staff enter it. |
| Owner | The one staff member responsible. A Case without an owner is in **Unassigned**. |
| Queue | A group of staff who share Cases of certain types by taking turns (Sales, Spare parts, General). |
| Stage | One of the five TOCO steps 01 to 05. |
| Mini step | A checklist item inside a stage, such as "Vessel booked". |
| SLA / deadline | The time we promise for a reply or a step, for example "first reply within 2 business hours". |
| Stale | An open Case with no staff activity for 3 days. |
| State | Open, Waiting on customer, On hold, Won, Lost or Closed. |

---

## 3. Your day: My work

Open **CRM → My work** every morning and keep it open. It refreshes every minute.

| Box | What it shows | What to do |
|---|---|---|
| Deadlines (nearest first) | Your Cases with a running reply or step deadline. Red = late, amber = due soon. | Work from the top. |
| Follow-ups due today or overdue | Cases whose next follow-up date has come. | Contact the buyer, then set a new follow-up date. |
| Tasks due | Your tasks due today or overdue. | Do them and click Done. |
| New for you (24 h) | Cases given to you in the last day. | Reply first. |
| No activity for 3+ days | Your stale Cases. | Move them forward, or close them. |
| Unassigned | Cases nobody owns yet. | Open one and click **⋮ → Take this Case**. |
| Staff away (admins) | Open Cases of staff who are away. | Reassign them (section 14). |
| Shipping and documents (Operations) | Vehicle sales at stages 03 and 04. | Tick the shipping and document steps. |

You also receive a **morning summary email** at 08:30 Japan time with your late replies, follow-ups, tasks and stale Cases.

---

## 4. Working a Case

Open a Case from **CRM → Cases** or from any list. The page shows:

- **Customer:** name, email, phone, WhatsApp link, country, and "Returning customer (n purchases)" for repeat buyers.
- **Enquiry:** type, source, vehicle (with "Dealer stock" when it is a supplier's car), destination port, value, payment method.
- **Owner and status:** who owns it, the nickname buyers see, when the customer last wrote, when we last replied, and the next follow-up date (red when overdue).
- **Deadline pills:** each running deadline, green, amber or red.
- **The five-stage bar** with the time spent in each stage.
- **Checklist** of mini steps (vehicle sales).
- **Website records:** the quote, proforma, order or form behind the Case, with links to their admin screens.
- **Tasks** and **Documents**.
- **Timeline:** everything that happened, newest first. Use the filters (Messages, Emails, Notes, Calls & WhatsApp, Progress, Documents, System). Yellow entries are internal notes; the customer never sees them.

### Buttons

**Follow up**

| Action | Use it to |
|---|---|
| Email the customer | Send an email from a template. Replies come back to you and into the Case. Counts as your reply. |
| Message in their account | Write a message the buyer sees on their purchase page (also emailed). Counts as your reply. |
| Add note | Write an internal note. Never shown to the customer. |
| Log call / WhatsApp | Record a phone call, WhatsApp chat or meeting with the outcome. Counts as your reply. |
| Open WhatsApp chat | Opens WhatsApp with the customer's number. Log the chat afterwards. |
| Add task | A to-do with a due time. You get a reminder when it is due. |
| Upload document | B/L, invoice, export certificate, inspection certificate, photos. Choose whether the customer can download it. |

**Status**

| Action | Use it to |
|---|---|
| Set next follow-up | Every open Case must have one. |
| Waiting on customer | The ball is with the buyer. Reply deadlines pause until they write. |
| Put on hold | Pause until a date, with a reason (for example "buyer travelling until the 20th"). The Case reopens by itself on that date. |
| Back to open | End waiting or hold. |
| Change status | Spare-part and general Cases: New, In progress, Waiting on customer, Resolved, Closed. |
| Move to next stage | Vehicle sales. Refused while required steps are open; the message lists what is missing. |
| Move back one stage | Admins only, with a reason. Later steps are reopened. |
| Convert to vehicle sale | A general enquiry that turns out to be about a car. History is kept. |
| Mark lost | The sale did not happen. A reason is required. Any car hold for this buyer is released. |
| Close | Duplicate, spam, or not a buyer. Closed spam and duplicates never get emails. |
| Reopen | Admins: bring a finished Case back. |

**⋮ (more)**

| Action | Use it to |
|---|---|
| Assign / reassign | Admins and team leads: give the Case to someone else, with a note. Both people see the change in the timeline. |
| Take this Case | Take an unassigned Case. |
| Edit details | Vehicle, destination port, value, CIF or FOB, payment method, subject. |
| Merge into another Case | Two Cases of the same customer that are really one. Everything moves to the Case you keep. |
| Customer's view | See exactly what the buyer sees. |

---

## 5. The five TOCO steps (vehicle sales)

The same five steps the buyer sees on the website "How to buy" page. A Case moves to the next stage **by itself** when every required step of its stage is done.

### 01 Search & Order

| Step | Required | Buyer sees | Ticks itself when |
|---|---|---|---|
| Enquiry acknowledged (first reply sent) | Yes | No | You email, message, or log a call or WhatsApp |
| Vehicle availability confirmed with the supplier | Dealer stock only | Yes | Never: confirm with the supplier, then tick |
| Price quote sent | Yes | Yes | The quote is priced in the Quotes screen, a proforma is issued, an order is placed, or you send the "Price quote" template |
| LC proforma invoice issued | LC buyers only | Yes | The buyer or staff create a proforma |
| Customer confirms order | Yes | Yes | The buyer clicks "Confirm my order", accepts the quote, or places an order |
| Vehicle reserved for the customer | Yes | No | The car is held for this buyer. Ticking it by hand puts a hold on the car for the payment period. |

### 02 Payment

| Step | Required | Buyer sees | Ticks itself when |
|---|---|---|---|
| Invoice issued | Yes | Yes | An order exists |
| Payment received | Yes | Yes | PayPal payment, or bank transfer confirmed on the order screen. For TT and LC, tick it and enter the method and amount. |
| Payment receipt sent | Yes | No | The "payment received" email went out (it is the receipt) |
| Vehicle marked sold on the website | Yes | No | The car's status becomes Sold. Ticking it by hand marks the car sold. |

The buyer sees the payment due date (3 days by default), the bank details from Site settings, and can **upload a payment slip**. You get a message when a slip arrives. Check the bank, then tick "Payment received".

### 03 Car Shipment

| Step | Required | Buyer sees | Ticks itself when |
|---|---|---|---|
| Export certificate obtained | Yes | Yes | Operations tick it |
| Pre-shipment inspection done | Only for countries that need it | Yes | Operations tick it |
| Shipping method and vessel booked | Yes | Yes | Tick it with RoRo or container, vessel, voyage and sailing date. Or the vessel is entered on the order. |
| Vehicle delivered to the port of loading | Yes | No | Operations tick it |
| Loaded on vessel, B/L issued | Yes | Yes | Tick it with the B/L number and arrival date. The order becomes Shipped. |

### 04 Clearing

| Step | Required | Buyer sees | Ticks itself when |
|---|---|---|---|
| Original documents sent by courier | Yes | Yes | Tick it with the courier (or "Telex release") and tracking number |
| Customer confirms documents received | Yes | Yes | The buyer clicks "Documents received", or you tick it |

### 05 Car Received

| Step | Required | Buyer sees | Ticks itself when |
|---|---|---|---|
| Vessel arrived at the destination port | Yes | Yes | Tick it with the arrival date |
| Customer confirms car received | Yes | Yes | The buyer clicks "Car received", or you tick it. The Case closes as **Won** and the order becomes Delivered. |
| Review / feedback requested | Optional | No | 3 days after Won, a thank-you email with the review link is sent |

**Undo.** A ticked step can be reopened with "Undo" if it was ticked by mistake.

**Data asked when ticking.** Steps that carry information (vessel, B/L number, courier, payment method) ask for it. The buyer sees it on their page and in their emails.

---

## 6. Spare parts and general enquiries

These do not use the five steps. Their status goes **New → In progress → Waiting on customer → Resolved → Closed**. Your first reply moves a New Case to In progress. A general enquiry about one of our cars can be converted to a vehicle sale (Status → Convert to vehicle sale).

A contact-form message that names one of our stock numbers (for example "Is E02062 available?") becomes a vehicle sale automatically.

---

## 7. Emails, templates and buyer replies

- **Templates.** Pick one in "Email the customer", edit the text, send. Placeholders such as `{customer_name}`, `{vehicle}`, `{price}`, `{port}`, `{due_date}`, `{portal_url}` and `{sender_name}` are filled in for you.
- **Bank details.** Never type account numbers. Write `{bank_details}`; the CRM fills in the details from Site settings, so nobody can send wrong or fake bank details. An email with typed account numbers is refused.
- **Sender.** Emails come from the TOCO address with your nickname ("Ken · TOCO Japan"). **Replies go to your own inbox** and the buyer's reply also appears in the Case within about 5 minutes.
- **Reference in the subject.** Every subject ends with the Case reference, for example `[C-2026-00042]`. This is how replies find their Case. Do not remove it.
- **Automatic emails to buyers** (switchable by an admin): "We received your enquiry", each new step, payment received (the receipt), vessel booked, B/L issued with arrival date, documents sent with tracking, vessel arrived, payment reminder one day before the due date, and the thank-you with review request.

---

## 8. Calls and WhatsApp

WhatsApp messages cannot be read automatically. After every WhatsApp chat or call, click **Log call / WhatsApp** and choose the outcome. This counts as your reply, keeps the history complete, and stops the reply deadline. The **Open WhatsApp chat** button opens a chat with the customer's number.

---

## 9. Follow-ups and tasks

- **Every open Case needs a next follow-up date.** New Cases get one 2 days ahead. Change it whenever you agree something with the buyer.
- **Tasks** are for specific to-dos ("Send auction sheet", "Call bank"). You get a reminder (bell and email) at the due time. Overdue tasks are red on My work and on **CRM → Tasks**.

---

## 10. Documents and payment slips

Upload documents on the Case (Follow up → Upload document). Choose **Customer can download it** for the proforma, invoice, B/L copy, export certificate and inspection certificate. They appear on the buyer's purchase page. Keep internal papers hidden. Use **Show / hide** next to a document to change it later.

Payment slips uploaded by the buyer appear under Documents, marked "from customer".

---

## 11. What the buyer sees

Buyers open **My purchases** in their tocojapan.com account, or the private link in any of our emails (guests without an account use the link). For each purchase they see:

- the five-step tracker with the steps marked "Buyer sees" above, with dates
- vessel, sailing date, B/L number, arrival date and courier tracking as you enter them
- the next thing they need to do, for example "Payment due by 9 Oct"
- buttons: Confirm my order, Pay now, Documents received, Car received
- bank details and payment slip upload at step 02
- documents you marked visible
- a message box to their TOCO contact (shown by nickname)

On the vehicle page, a logged-in buyer sees "Your enquiry: Stage 01 Search & Order" when they already have an open Case for that car. A buyer who enquired as a guest and later registers with the same email sees the earlier Cases in their account.

---

## 12. What happens automatically from the website

| On the website | In the CRM |
|---|---|
| Quote request (website or app) | New vehicle-sale Case at 01, or joins the buyer's open Case for that car |
| Quote priced or accepted in the Quotes screen | "Price quote sent" or "Customer confirms order" ticked |
| Quote message from the buyer | Added to the timeline; the owner is told |
| LC proforma invoice | New or existing Case at 01; payment by LC; "Proforma issued" ticked |
| Buy now (PayPal or bank transfer) | Case moves to 02 Payment |
| PayPal payment / bank transfer confirmed on the order | "Payment received" ticked; receipt emailed |
| Order marked Shipped (with B/L) | "Vessel booked" and "B/L issued" ticked |
| Order marked Delivered | Remaining steps ticked; Case Won |
| Order cancelled or refunded | Note on the timeline and the owner told. The Case stays open: decide Lost or carry on. |
| Contact form | General Case (vehicle sale if it names a stock number) |
| Spare-part request | Spare parts Case |
| Car reserved or sold for another buyer | Owners of other open Cases on that car are warned |
| Buyer replies to our email | Added to the Case within about 5 minutes |
| Buyer at stage 02 to 05 | Left out of stock "hot deals" email campaigns (hourly) |

The order, quote and enquiry screens show a **"CRM Case C-…"** button that opens the Case.

---

## 13. Deadlines (service levels)

Reply deadlines count **Japan business hours** only: Monday to Friday, 09:00 to 18:00, without Japanese public holidays. A Case created Friday at 17:30 with a 2-hour target is due Monday at 10:30.

| Deadline | Default | Clock |
|---|---|---|
| First reply | 2 business hours | From the enquiry to the first email, message, call or WhatsApp log |
| Reply to a customer message | 1 business day (9 hours) | From the buyer's message to our next reply |
| Quote sent | 1 business day | From the enquiry |
| Payment | 3 days | From reaching stage 02; the buyer is reminded 1 day before |
| Vessel booked | 10 days | From reaching stage 03 |
| Documents couriered | 5 days | From the B/L |
| Stale | 3 days without staff activity (owner told); 5 days (admins told) | |
| Unassigned | 30 business minutes (admins told) | |

- **Warning** at 75% of the time to the owner. **Breach** alerts go to the owner and the admins.
- "Waiting on customer" and "On hold" **pause** reply deadlines.
- Colours everywhere: green on time, amber due soon, red late.
- Results are kept after a Case closes, for the team overview and reports.

---

## 14. Assignment and queues (admins)

New Cases go to the matching queue (**CRM → Queues**). Members take turns:

- People who are **away** or at the queue's **limit of open Cases** (30 by default) are skipped.
- A **returning buyer** goes to the staff member who handled them before, if available.
- When nobody is available the Case stays **Unassigned** and the admins are told. After 30 business minutes they are told again by email.

**To reassign several Cases** (for example when someone is on leave): CRM → Cases, filter by Owner, tick the Cases, then **Assign to…**. Each change is recorded.

---

## 15. Notifications

The bell (top right of the admin) shows CRM alerts; click one to open the Case. Emails follow the same rules. Admins can switch each one on or off per channel in **CRM → Settings → Notifications**.

| Event | Owner / staff | Admins | Buyer |
|---|---|---|---|
| New Case | Bell + email | Bell + email when unassigned | "We received your enquiry" |
| Case assigned | Bell + email | Bell (reassignments) | |
| Customer message | Bell + email | When unassigned | |
| Reply due soon (75%) | Bell + email | | |
| Deadline missed | Bell + email | Bell + email | |
| No activity 3 days / 5 days | Bell + email | Bell + email (5 days) | |
| Task due | Bell + email to the task's person | | |
| Stage changed | Bell | | Email |
| Payment received | Bell + email | Bell | Email (receipt) |
| Vessel booked, B/L issued, documents sent | Bell | | Email |
| Vessel arrived | | | Email |
| Won | Bell | Bell | Thank-you and review request (3 days later) |
| Lost | Bell | Bell | |
| Payment due soon | Bell | | Reminder email |
| Car taken by another buyer | Bell + email | | |
| Morning summary | Email at 08:30 | Team summary at 08:30 | |

Every email sent or failed is logged. A failed email is retried and never stops a Case from being saved.

---

## 16. Customers

**CRM → Customers** lists everyone who contacted TOCO, with open Cases, purchases and last activity. A customer's page shows contact details, every Case with its stage, total purchases and the full history. **Merge a duplicate into this customer** joins two records of the same buyer (for example two email addresses); all Cases move to the one you keep.

---

## 17. Dashboards and reports (admins and team leads)

| Screen | Shows |
|---|---|
| Dashboard (everyone) | Coloured tiles: open Cases, new this week, late replies, unassigned, pipeline value, cars sold this month, conversion (90 days). The five stages with how many open car sales and how much value sit in each, and how many are late. Charts: new Cases and cars sold per week (12 weeks), sales value by month (6 months), where enquiries come from (90 days), open Cases by status. "Needs attention": late replies first, then Cases without an owner, then overdue follow-ups. Agents see their own Cases, admins see all. Click a tile or a stage to open the matching list. |
| Pipeline | Every open vehicle sale in five columns, with customer, car, value, owner, days in stage and a coloured edge for the deadline. Filter by owner, source, country, dealer stock and dates. |
| Team overview | For each staff member: open Cases per stage, average first-reply time, replies on time (%), overdue follow-ups, stale Cases, last activity, cars won this month. |
| SLA monitor | Late and at-risk Cases, refreshed every minute. |
| Sales reports | Funnel (how many Cases reached each step), lost reasons, sources and conversion, average time in each stage, response times, cars sold by month and by country. Every table downloads as CSV for Excel. |

**Saved views.** On the Cases, Customers and Tasks lists, set your filters and search, then **Views → Save this view**. Your views appear in the same menu next time.

**Search** in the Cases list finds a reference, customer name, email, phone, stock number, chassis number, order number or B/L number.

---

## 18. Settings (admins)

| Where | What you can change |
|---|---|
| CRM → Settings → Business hours | Working hours per weekday and holidays |
| CRM → Settings → Service levels | Every deadline in section 13, the warning %, stale days, unassigned alert time, default follow-up |
| CRM → Settings → Customer emails | Team name buyers see, nicknames on or off, review link and delay, payment reminder, whether CRM emails replace the old order emails, buyer replies on or off, extra inboxes for admin alerts |
| CRM → Settings → Lost and close reasons | The reason lists |
| CRM → Settings → Notifications | Each event on or off for bell, staff email and buyer email |
| CRM → Stages & steps | Add, rename, reorder or retire mini steps; required or optional; visible to the buyer; done by Operations; only for dealer stock, LC or inspection countries; data to ask for when ticked |
| CRM → Email templates | Wording of the automatic buyer emails and of the staff templates |
| CRM → Queues | Queues, members, case types and sources, open-case limit |
| CRM → My profile | Nickname, time zone, emails, away dates (admins can open anyone's with `?user=ID`) |

New deadline settings apply to Cases created after the change.

**Adding a new staff member:**

1. Admin → System → Users: create the user with their own email and give one role: crm_agent, crm_team_lead, crm_operations or crm_admin.
2. CRM → Queues: add them to the right queue.
3. Ask them to set their nickname in CRM → My profile.

**When someone leaves:** remove them from their queues, reassign their open Cases (section 14), then remove their CRM role. Their history stays with the Cases.

---

## 19. Questions and answers

**A buyer says they did not get our email.** Check the Case timeline for the email, and the spam folder on their side. Every send and failure is logged by IT.

**A Case is red but I already replied on WhatsApp.** Log the chat with "Log call / WhatsApp". Only logged contact stops the deadline.

**The Case will not move to the next stage.** Click "Move to next stage": the message lists the open required steps.

**Two Cases for the same buyer and car.** Use ⋮ → Merge into another Case.

**The same buyer has two email addresses.** Open one customer and use "Merge a duplicate into this customer".

**I ticked a step by mistake.** Click "Undo" next to it.

**A buyer wants to cancel.** Mark the Case lost with the reason. The hold on the car is released so it returns to sale.

**The car is sold to someone else.** Owners of the other open Cases on that car get a warning. Offer those buyers another car, or mark the Cases lost with "Vehicle sold to someone else".

**Who sees my notes?** Only staff. Buyers never see internal notes.

**A Case is spam.** Close it with the reason Spam. No emails are sent to it.
