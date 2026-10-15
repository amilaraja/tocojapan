# TOCO CRM: end-to-end test and demo recording script

For Amila to run on the live site (tocojapan.com) and record with voice for TOCO.
Each scene has **DO** (what you click) and **SAY** (what you narrate). Running time about 15–18 minutes;
record scene by scene and join the clips, or pause the recorder between scenes.

Everything below uses a **test car** and **test accounts**, so no real buyer is emailed and no real car
is reserved or marked sold. Clean-up is at the end.

---

## 0. Before you record (10 minutes, not recorded)

**Three browser windows, side by side or on separate desktops:**

| Window | Who | Browser |
|---|---|---|
| A | You as **admin** (admin@tocojapan.com) | Chrome, normal |
| B | **Test sales agent** | Chrome, second profile or Edge |
| C | **Test buyer** | Firefox or a private window |

**Email inboxes:** use Gmail "plus" addresses so everything lands in your own Gmail:
- agent: `amilaraja+tocoagent@gmail.com`
- buyer: `amilaraja+tocobuyer@gmail.com`

**Set-up steps (window A, admin):**

1. **Test car.** Admin → Vehicles → New. Title `TEST CAR – CRM demo (do not buy)`, any make/model, year 2018,
   price $1,000, a size so CIF can be worked out (e.g. 4.5 × 1.7 × 1.5 m), status **Published**, one photo.
   Note its stock number.
2. **Test agent.** Admin → System → Users → New. Name `Demo Agent`, email `amilaraja+tocoagent@gmail.com`,
   a password, role **CRM Agent**.
3. **Put the agent in the sales queue.** CRM → Queues → Sales → add Demo Agent as a member.
   (Now new car enquiries go to the agent automatically.)
4. **Agent profile (window B).** Log in to tocojapan.com/crm as the agent. CRM → My profile:
   nickname **Ken**, time zone Asia/Colombo, emails "As they happen". Save.
5. **Test buyer (window C).** On tocojapan.com, Register with `amilaraja+tocobuyer@gmail.com`, name `Demo Buyer`,
   country Sri Lanka. Verify the email if asked.
6. Check CRM → Settings → Notifications: buyer emails switched on (default).
7. Close any other admin tabs so the bell and lists are clean on screen.

> Tip: zoom the browsers to 110–125 % so text is readable in the video.

---

## Scene 1 — Welcome and the Dashboard (1.5 min) · window A

**DO:** Open tocojapan.com/crm → **Dashboard**. Hover over the tiles, then the pipeline strip, then scroll to the charts and "Needs attention".

**SAY:**
> "Hello TOCO team. This is your new sales tracking system, the CRM, now live in its own workspace at tocojapan.com/crm.
> This is the CRM Dashboard, the first thing your team sees every morning.
> At the top: open enquiries, new ones this week, late replies, enquiries that still need an owner, the value of
> every car sale in progress, cars sold this month and your conversion rate.
> Below, your five TOCO steps — Search and Order, Payment, Car Shipment, Clearing and Car Received — with how many
> sales sit in each step and how many are late.
> The charts show new enquiries against cars sold each week, sales value by month and where your enquiries come from.
> And 'Needs attention' lists exactly what someone should handle next.
> Let me show you one complete sale, from the buyer's first enquiry until the car is received."

---

## Scene 2 — The buyer asks for a price (1.5 min) · window C

**DO:** As Demo Buyer, open the TEST CAR page → **Get a CIF estimate** → country Sri Lanka, port Hambantota,
a short message "Is this car still available?" → Send.

**SAY:**
> "I'm now a buyer on your website. I open a car and ask for a price, exactly as your customers do today.
> Nothing changes for the buyer — but watch what happens on your side."

---

## Scene 3 — The enquiry arrives and is assigned (1.5 min) · windows A and B

**DO:** Window A: CRM → Cases (wait up to a minute, it refreshes). Open the new Case. Point at the reference
(C-2026-…), the customer, the car, the owner **Demo Agent**, the deadline pill and the five-stage bar.
Window B: show the bell notification and the "New Case" email in Gmail.

**SAY:**
> "Within a minute the enquiry appears in the CRM as a Case with its own reference number.
> It was given automatically to the next sales person in the queue — here, our agent — who got a notification
> and an email. A returning buyer would go back to the person who helped them before.
> The green pill is the reply deadline: two business hours in Japan time. If nobody replies in time,
> it turns amber, then red, and you as the manager are told."

---

## Scene 4 — The agent replies, with a nickname (2 min) · windows B and C

**DO:** Window B: CRM → **My work** (show "New for you"). Open the Case → Follow up → **Email the customer** →
template **Price quote** → show the filled-in text → Send. Show the checklist: "Enquiry acknowledged" and
"Price quote sent" are ticked. Then **Log call / WhatsApp** → WhatsApp, outcome "Interested" → Save.
Window C / Gmail: open the buyer's email — the sender shows **Ken · TOCO Japan**.

**SAY:**
> "Each staff member starts the day on My work: deadlines, follow-ups and new Cases, nearest first.
> The agent replies with a ready-made template. Names, car, price and port are filled in automatically.
> The checklist ticks itself: enquiry acknowledged, price quote sent, and the deadline turns green-done.
> The buyer receives the email from 'Ken' — a nickname. Your staff's real names stay private, and replies go
> straight to Ken's own inbox and into the Case.
> WhatsApp chats can't be read automatically, so after a chat the agent taps 'Log call / WhatsApp'.
> That keeps the full history in one place."

---

## Scene 5 — The buyer follows their purchase (1.5 min) · window C

**DO:** Buyer: Account menu → **My purchases** → open the purchase. Show the five-step tracker, "Your TOCO contact: Ken",
the message box. Click **Confirm my order**.
Optional: reply to Ken's email from Gmail ("Yes, I want it"); about 5 minutes later it shows in the Case timeline.

**SAY:**
> "Your buyer now has a 'My purchases' page. They see the same five steps you see, with dates, their contact person
> by nickname, and they can message you directly.
> The buyer confirms the order with one click. And if they simply reply to our email, that reply lands in the Case
> by itself within about five minutes."

---

## Scene 6 — Reserve the car and move to Payment (1.5 min) · window B

**DO:** Case page: the "Customer confirms order" step is now ticked. Tick **Vehicle reserved for the customer**.
The Case moves to **02 Payment** by itself. Show the stage bar and the timeline. Point at "Invoice issued" and
tick it if it is still open.

**SAY:**
> "The buyer confirmed, so that step is done. The agent reserves the car — this also holds it on the website so
> nobody else can buy it, and any other sales person talking to another buyer about this car is warned.
> When every required step is done, the sale moves to Payment by itself. Nobody has to remember to move it."

---

## Scene 7 — Payment and the payment slip (2 min) · windows C and B

**DO:** Window C: My purchases → the payment step shows the due date and **your bank details** → **Upload slip**
(any image) → Upload.
Window B: the bell shows the slip notification → Case → Documents shows the slip "from customer".
Tick **Payment received** → method TT, amount 1000 → Save. Then tick **Vehicle marked sold on the website**.
Show that "Payment receipt sent" ticked itself and the Case moved to **03 Car Shipment**.

**SAY:**
> "At Payment the buyer sees the amount, the due date and your bank details. Those bank details always come from
> your settings — staff can't type account numbers into emails — which protects your buyers from fake bank details.
> The buyer uploads the bank slip, and the agent is told immediately.
> After checking the bank, the agent ticks 'Payment received'. The buyer gets a receipt email automatically,
> the car is marked sold on the website, and the sale moves to Car Shipment."

---

## Scene 8 — Shipping and documents (2 min) · window B (or an Operations user)

**DO:** Tick **Export certificate obtained**. Tick **Shipping method and vessel booked** → RoRo, vessel "TEST VESSEL",
voyage 001, sailing date next week. Tick **Vehicle delivered to the port of loading**. Tick **Loaded on vessel,
B/L issued** → B/L number TEST-BL-001, arrival date in a month. Follow up → **Upload document** → any PDF, type **Bill of Lading (B/L)**, **Customer can download it**. The Case moves to **04 Clearing**.
Window C: refresh My purchases — vessel, B/L number, arrival date and the B/L download appear.

**SAY:**
> "Your shipping and documents team works from the same Case. Booking the vessel, the B/L number and the arrival
> date — each one is entered once, and the buyer sees it on their page and gets an email.
> Documents you mark as visible, like the B/L copy, can be downloaded by the buyer. Internal papers stay hidden."

---

## Scene 9 — Clearing and car received (1.5 min) · windows B and C

**DO:** Window B: tick **Original documents sent by courier** → DHL, tracking TEST123.
Window C: click **Documents received** → the Case moves to **05 Car Received**.
Window B: tick **Vessel arrived at the destination port** → today.
Window C: click **Car received** → the Case closes as **Won**.

**SAY:**
> "The courier and tracking number go to the buyer automatically. The buyer confirms the documents arrived,
> and later that the car arrived. The sale closes as Won. Three days later the buyer gets a thank-you email
> asking for a review."

---

## Scene 10 — Back to the manager's view (2 min) · window A

**DO:** CRM → **Dashboard**: "Sold this month" and the charts now include the test sale.
CRM → **Pipeline** (the board, filters by owner/source/country). CRM → **Team overview**. CRM → **SLA monitor**.
CRM → **Sales reports** → download one table as CSV. CRM → **Cases** → show search (type the stock number or B/L).
Briefly: a quote or order screen shows the **CRM Case** button.

**SAY:**
> "Back on the dashboard, the sale is counted. The Pipeline board shows every sale by step.
> Team overview shows each person's reply speed and results. The SLA monitor lists anything late or at risk,
> refreshed every minute. Sales reports show sales by country, source and month, with lost-sale reasons,
> and every table downloads to Excel. One search box finds any sale by name, email, phone, stock number,
> chassis or B/L number."

---

## Scene 11 — Settings, help and closing (1 min) · window A

**DO:** CRM → **Settings** (working hours, deadlines, notifications). CRM → **CRM Help**. End on the Dashboard.

**SAY:**
> "Working hours, deadlines and notifications are all adjustable here. The full operating guide is built in,
> under CRM Help.
> Two more things happen in the background: buyers in the middle of a purchase are left out of 'hot deals'
> stock emails, and every morning at 8:30 each person receives a short summary of what needs doing.
> To start, please send us your staff list — names, emails, roles and the nickname each person would like —
> and we'll set up everyone's login and arrange a short training session. Thank you."

---

## After recording: clean-up (not recorded)

1. **Test Case:** CRM → Cases → the test Case → **Reopen** (admin) → **Close (duplicate, spam …)** with reason "test",
   so it is not counted in sales reports or "Sold this month".
2. **Test car:** Admin → Vehicles → TEST CAR → set to Draft, or delete it.
3. **Test agent:** remove from the Sales queue, then Admin → System → Users → remove the CRM role or delete
   Demo Agent (otherwise real enquiries would be assigned to it).
4. **Test buyer:** Admin → System → Users → delete Demo Buyer if you like.
5. Check CRM → Dashboard: the test sale is gone from "Sold this month".

## What to check while testing (pass / fail)

| # | Check | Pass when |
|---|---|---|
| 1 | Enquiry arrives | Case appears within a minute, assigned to Demo Agent |
| 2 | Agent alerted | Bell + email to the agent |
| 3 | Buyer acknowledgement | Buyer receives "We received your enquiry" |
| 4 | Template reply | Email arrives from "Ken · TOCO Japan", subject ends with [C-2026-…] |
| 5 | Auto-ticks | Enquiry acknowledged and Price quote sent tick themselves |
| 6 | Buyer portal | My purchases shows the purchase, contact "Ken", five steps |
| 7 | Buyer reply by email | Appears in the Case timeline within ~5 minutes |
| 8 | Auto stage moves | 01→02→03→04→05 happen by themselves when steps are done |
| 9 | Payment slip | Upload works; agent alerted; slip under Documents |
| 10 | Receipt | Buyer gets the payment received email |
| 11 | Car status | Test car shows Reserved, then Sold on the website |
| 12 | Shipping emails | Buyer gets vessel booked, B/L issued, documents sent emails |
| 13 | Document download | Buyer can download the B/L copy |
| 14 | Won | Case closes as Won after "Car received"; dashboard counts it |
| 15 | Clean-up | Test Case closed, test car hidden, test agent out of the queue |
