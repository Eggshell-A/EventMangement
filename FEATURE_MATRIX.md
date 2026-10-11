# Feature matrix

| Feature | User Story / USP | Original release | Current status | Notes |
|---|---|---|---|---|
| Registration, login, roles | US1 | R1 | Complete | Admin manually assigned in database |
| Create/edit/cancel own event | US2 | R1 | Complete | New events start pending |
| Public approved event list/detail | US4 / admin approval | R1 | Complete | Enforced in server query |
| Admin approve/reject/remove | US10 / admin approval | R2 | Complete | Moved into MVP |
| Booking / My Bookings / cancel | US5 | R1 | Complete | One booking DB constraint |
| Capacity / remaining count | USP supply-demand | R1 | Complete | Concurrency lock deferred |
| Basic FIFO waitlist | US6 | R2 | Partial | No automatic offer or expiry; a queued attendee cannot currently claim reopened capacity manually |
| Dashboard / Add Places | US8 | R2 | Complete | Capacity only increases |
| Attendee list / manual check-in | US9 | R1/R2 | Complete | Owner/admin only |
| QR and A4 poster | US3 / low-resource | R2 | Partial | Implemented with chillerlan/php-qrcode 5.0.5; QR loads in browser, physical/PDF print verification pending |
| CSRF / CSP / rate limit | Security | N/A | Deferred | Security hardening required |
| Search, images, email | US4 / later | R2 | Deferred | Out of MVP scope |
