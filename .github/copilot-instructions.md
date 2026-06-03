# Copilot Instructions

These instructions apply to code reviews on pull requests in this repository.

## Project Context

This is PayPlug's official WooCommerce plugin. PayPlug is a Payment Service Provider (PSP). The plugin integrates PayPlug's payment processing into WooCommerce via multiple payment methods: standard card (PayPlug), Oney financing, Bancontact, Scalapay, Apple Pay, and American Express.

**Stack**: PHP 8.0+, WordPress 6.0+, WooCommerce 7.0+, `payplug/payplug-php ^4.0`

## Intentional Patterns — Do Not Flag as Issues

- **`sleep(10)` in IPN/notification handlers** — intentional, prevents a race condition between the IPN webhook and the customer redirect. Never suggest removing it.
- **No direct SDK calls** — `PluginClientFactory` / the gateway's API client wrapper is the only allowed entry point to the PayPlug PHP SDK. Any direct call to SDK classes bypasses this and is a bug.
- **Card saving condition** — a card is only saved when the PayPlug API response includes `metadata['customer_id']`. Absence of this guard is a bug.
- **`payment_context.cart`** — required in the PayPlug API payload for Oney and Scalapay payments. Missing it causes API rejection.
- **WooCommerce order locking** — WooCommerce's `wc_transaction_query` / order-level locking is used in IPN handlers to prevent concurrent webhook processing. Do not flag as over-engineering.
- **Custom gateway classes** — each payment method extends `WC_Payment_Gateway` (or a shared base class). Duplicate-looking methods across gateway classes are intentional; shared logic lives in the base class only.

## Code Review Dimensions

### Security
- SQL injection (use `$wpdb->prepare()` — never interpolate user input into queries), XSS (`esc_html`, `esc_attr`, `wp_kses`), CSRF (nonce verification with `check_ajax_referer` / `wp_verify_nonce`)
- Authentication and authorization flaws (`current_user_can()` checks before any privileged action)
- Secrets or credentials committed in code
- Insecure deserialization, path traversal, SSRF
- Direct calls to PayPlug PHP SDK classes instead of going through the plugin's API client wrapper
- IPN/webhook payloads must be verified via the SDK's `treat()` method before any processing — never act on raw `php://input` directly
- Card data (PAN, CVV, raw card numbers) must never appear in logs, error messages, order notes, or stored meta
- API secret keys must never appear in logs, exception messages, or HTTP responses
- Payment amounts must be validated server-side — never trust a client-submitted amount
- `redirect_url` values must come from the PayPlug API response, never constructed from user input (open redirect risk)
- Settings saved via `woocommerce_update_options_payment_gateways_*` hooks must sanitize all fields before persistence

### Performance
- N+1 queries (especially in Sylius entity traversal)
- Unnecessary memory allocations
- Algorithmic complexity (O(n²) in hot paths)
- Missing database indexes
- Unbounded queries or loops
- Resource leaks

### Correctness
- Edge cases: empty input, null, overflow
- Race conditions and concurrency issues (IPN and order status updates)
- Error handling and propagation
- Off-by-one errors, type safety
- `declare(strict_types=1)` must be present in every PHP file
- For new payment gateways: verify the full implementation checklist is covered (gateway class extending `WC_Payment_Gateway`, `init_form_fields`, `process_payment`, IPN handler, refund support via `process_refund`, settings registration in `woocommerce_payment_gateways`, translations)
- Amount unit: PayPlug API works in the smallest currency unit (cents). WooCommerce amounts are in major units (euros). Any conversion must be explicit — silently mixing units is a payment amount bug
- Currency enforcement (EUR-only or allowed-currency list) must happen before the API call, not after
- Refund amounts must not exceed the remaining refundable amount on the payment
- Order status transitions must use WooCommerce constants (`wc-pending`, `wc-processing`, `wc-completed`, `wc-failed`, etc.) — hardcoded strings are fragile
- `woocommerce_api_*` endpoint registration must match the IPN URL stored in the PayPlug payment — a mismatch causes silent IPN loss

### Maintainability
- Naming clarity, single responsibility, duplication
- Test coverage: PHPUnit in `tests/`
- Documentation for non-obvious logic only — do not flag missing comments on self-explanatory code
- PHPStan compliance; suppressions must go in the baseline file
- WordPress/WooCommerce coding standards (PHPCS with `WordPress` ruleset)
- Translations must be complete and use `__()` / `_e()` with the plugin's text domain — hardcoded strings are a regression
- New hooks (`add_action`, `add_filter`) must be registered at the correct priority and removed symmetrically when their gateway is disabled
- Plugin options must use namespaced keys (`payplug_*`) to avoid collisions with other plugins

### Headless / REST Compliance

When WooCommerce is used in headless mode (decoupled frontend via WooCommerce Blocks or the REST API), AJAX and REST endpoints must return JSON, not server-side redirects.

- AJAX handlers (`wp_ajax_*`, `wp_ajax_nopriv_*`) and REST route callbacks in the payment flow must return `wp_send_json_success()` / `wp_send_json_error()` with a `redirect_url` field; the client performs the redirect.
- Classic `wp_redirect()` / `wp_safe_redirect()` are acceptable only in non-AJAX, non-REST flows (e.g. the standard WooCommerce return URL handler).
- Admin AJAX handlers are exempt — headless compliance only applies to the checkout and payment flow.
- If a new AJAX or REST handler in the payment flow returns a redirect instead of JSON, flag it as a headless compliance issue.

## Output Format

Structure the review comment exactly as follows:

### 1. What's Good

A bullet list of positive observations — things done well, non-obvious correct decisions, solid patterns.

---

### 2. Summary table

A markdown table with two columns: **Dimension** and **Rating**. One row per review dimension. Use emoji inline with the rating text:

| Dimension | Rating |
|---|---|
| Security | ✅ Fine |
| Correctness | ⚠️ Medium (short reason) |
| Performance | ✅ Fine |
| Maintainability | ⚠️ Low (short reason) |

Severity scale:
- ✅ **Fine** — no issues
- ⚠️ **Low / Medium** — should be fixed but not blocking
- ❌ **High / Critical** — must be fixed before merge

---

### 3. Closing one-liner

A single sentence summarising what needs to be addressed before merge (or that the PR is ready if nothing critical).

---

### 4. Individual findings (one section per issue)

Each finding follows this exact structure:

**Heading:** `[Dimension] [emoji] [Severity]` — e.g. `Security ⚠️ Medium`

**Subtitle (bold):** short title followed by the file path and line number as a markdown link — e.g. `**Path traversal in getPayment** (PaymentClient.php:290)`

**Code block:** the relevant snippet from the diff showing the problem.

**Explanation paragraph:** what the risk is and why it matters. Be concrete.

**Fix line:** start with `Fix:` in bold, then a brief description, followed by a code block showing the suggested fix.

Lead with Critical/High findings. Omit the findings section entirely if there are no issues.

## Iterative Reviews

When reviewing a new commit on a PR that already has open review threads:

- **Resolve threads** for issues that have been addressed in the new commit — do not leave them open if the fix is present.
- **Do not re-open or re-comment** on issues that were already resolved in a previous round.
- Only open new threads for issues that are genuinely new or that remain unresolved.
- If a previous finding was partially addressed, update the thread with what still needs attention rather than opening a duplicate.
