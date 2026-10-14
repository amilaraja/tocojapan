# Prompt for Claude Desktop (computer use): TOCO CRM end-to-end test

How to use:
1. Do the **set-up** below first (or ask Claude Code on the server to create the test car and accounts).
2. Open three browser windows and log in yourself (you type the passwords, not Claude):
   - **Chrome (normal window)** → tocojapan.com/admin as **admin@tocojapan.com**
   - **Chrome Incognito window** → tocojapan.com/admin as **Demo Agent** (amilaraja+tocoagent@gmail.com)
   - **Firefox (or Edge)** → tocojapan.com, logged in as **Demo Buyer** (amilaraja+tocobuyer@gmail.com)
   - A fourth tab with **Gmail** (amilaraja@gmail.com) open.
3. Start your screen recorder if you want the run on video.
4. In Claude Desktop, switch on computer use, paste everything between the lines, fill in the stock number, and send.

Set-up needed (once): a published test car "TEST CAR – CRM demo (do not buy)" ($1,000, with a size so CIF works);
user Demo Agent with role CRM Agent, member of CRM → Queues → Sales, nickname "Ken" in CRM → My profile;
user Demo Buyer (normal website account, country Sri Lanka). Details: docs/crm/CRM-demo-test-script.md §0.

---------------------------------------------------------------------------------------------------

You are testing the TOCO CRM on the live website tocojapan.com, end to end, by using my computer.
Three browser windows are already open and logged in:

- **ADMIN**: Chrome normal window, tocojapan.com/admin, logged in as the site admin.
- **AGENT**: Chrome Incognito window, tocojapan.com/admin, logged in as "Demo Agent" (nickname Ken).
- **BUYER**: Firefox, tocojapan.com, logged in as "Demo Buyer".
- **GMAIL**: a Gmail tab (amilaraja@gmail.com). Emails to the agent and buyer arrive here, addressed to
  amilaraja+tocoagent@gmail.com and amilaraja+tocobuyer@gmail.com.

The test car is stock number **[FILL IN, e.g. E02099]**, titled "TEST CAR – CRM demo (do not buy)".

### Safety rules (follow strictly)
- Only work with the TEST CAR, the Case created in this test, Demo Agent and Demo Buyer. Never open, edit, reply to,
  assign or close any other Case, customer, order or vehicle.
- Never change anything in Site settings, CRM → Settings, Users, Queues or other vehicles.
- Never type passwords, card numbers or bank account numbers. If a login screen appears, stop and ask me.
- Do not pay with PayPal or anything real. Payment is simulated with a slip image and a staff tick.
- If something fails or looks wrong, take a screenshot, note it, and continue with the next step if possible.
  If you are blocked, stop and tell me.
- Wait patiently where the system works in the background: a new Case can take up to 1 minute to appear;
  emails up to 2 minutes; a buyer's email reply up to 5–6 minutes to reach the Case.

### Steps and what should happen

1. **ADMIN** → CRM → Dashboard. Note the numbers on the tiles "Open Cases" and "Sold this month".
2. **BUYER** → open the test car page (search the stock number on tocojapan.com/vehicles) → "Get a CIF estimate" →
   country Sri Lanka, port Hambantota, message "Is this car still available? (CRM test)" → send.
   Expect: a confirmation on screen.
3. **ADMIN** → CRM → Cases. Within about a minute a new Case appears for Demo Buyer with the test car.
   Open it. Note the reference (C-2026-…). Expect: owner **Demo Agent**, stage **01 Search & Order**,
   a reply deadline pill.
4. **GMAIL** → expect (a) a "new Case" email to the agent address and (b) "We received your enquiry" to the buyer address.
5. **AGENT** → CRM → My work → the Case is under "New for you". Open it.
   Follow up → **Email the customer** → template **Price quote** → send.
   Expect: checklist items "Enquiry acknowledged" and "Price quote sent" become ticked.
6. **GMAIL** → open the price quote email to the buyer address. Expect: sender name **Ken · TOCO Japan**,
   subject ending with the Case reference in brackets. Reply to it with "Yes, I would like this car (CRM test)".
7. **AGENT** → on the Case: Follow up → **Log call / WhatsApp** → WhatsApp, outcome Interested, note "CRM test" → save.
   Expect: a timeline entry.
8. **BUYER** → account menu → **My purchases** → open the purchase. Expect: the five steps, "Your TOCO contact: Ken".
   Click **Confirm my order**. Expect: step "Customer confirms order" shows as done.
9. **AGENT** → refresh the Case. Tick **Vehicle reserved for the customer** (confirm the dialog). If "Invoice issued" is
   open later, tick it. Expect: the Case moves to **02 Payment** by itself.
10. **BUYER** → My purchases → the payment step shows a due date and bank details. Use **Upload slip** with any small
    image file from my Pictures or Desktop folder. Expect: success message.
11. **AGENT** → expect a bell notification about the slip; the slip under Documents "from customer".
    Tick **Payment received** → payment method TT (bank transfer), amount 1000. Then tick
    **Vehicle marked sold on the website**. Expect: "Payment receipt sent" ticks itself, Case moves to
    **03 Car Shipment**, GMAIL gets a "Payment received" email to the buyer address.
12. **AGENT** → tick **Export certificate obtained**; tick **Shipping method and vessel booked** (RoRo, vessel
    "TEST VESSEL", voyage 001, sailing date one week from today); tick **Vehicle delivered to the port of loading**;
    tick **Loaded on vessel, B/L issued** (B/L number TEST-BL-001, arrival date one month from today).
    Follow up → **Upload document** → any small PDF or image, type "Bill of Lading (B/L)", tick
    "Customer can download it". Expect: Case moves to **04 Clearing**; buyer emails for vessel booked and B/L issued.
13. **BUYER** → refresh My purchases. Expect: vessel name, B/L number, arrival date and the B/L document download.
14. **AGENT** → tick **Original documents sent by courier** (courier DHL, tracking TEST123).
    **BUYER** → click **Documents received**. Expect: Case moves to **05 Car Received**.
15. **AGENT** → tick **Vessel arrived at the destination port** (today). **BUYER** → click **Car received**.
    Expect: the Case shows **Won**.
16. **AGENT** → open the Case timeline. Expect: the buyer's Gmail reply from step 6 is there (wait up to 6 minutes
    from when you sent it; refresh).
17. **ADMIN** → CRM → Dashboard. Expect: "Sold this month" is one higher than in step 1.
    Open CRM → Pipeline, Team overview, SLA monitor and Sales reports; each should load without errors.
    In CRM → Cases, search for TEST-BL-001; expect the test Case to be found.
18. **ADMIN** → on the test vehicle page on the website, expect status Sold.

### Report
At the end, give me a table: step number, what was checked, PASS / FAIL, and a short note
(for FAIL: what you saw, with the screenshot). Then list anything that felt slow or confusing.
Do not clean up; I will do it.

---------------------------------------------------------------------------------------------------

## After the run: clean-up (you, or ask Claude Code)
1. ADMIN → the test Case → **Reopen** → **Close (duplicate, spam …)**, reason "test", so it is not counted as a sale.
2. Test car → Draft or delete.
3. Demo Agent → remove from CRM → Queues → Sales (otherwise real enquiries get assigned to it), or delete the user.
4. Demo Buyer → delete if you like.
