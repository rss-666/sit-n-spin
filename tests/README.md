# Test Suite

`composer test` runs the dependency-free focused suite in `run.php`; `composer test:phpunit` runs the equivalent PHPUnit cases when development dependencies are installed. Both exercise the plugin's independently testable helpers. The bootstrap provides only the small WordPress function surface needed by those helpers; it does not fake provider success or replace production implementations.

For integration smoke testing, install the plugin on a disposable clean WordPress site and exercise:

1. activation/schema and role capability creation;
2. a valid local test RSS endpoint plus a malformed endpoint;
3. keyword/category/date filtering and normalized duplicate detection;
4. queue permissions and nonce failures using Administrator, Editor, and Subscriber users;
5. manual briefing editing and draft creation;
6. source attribution HTML and protected post meta;
7. provider authentication/429/5xx/invalid-JSON failures with a controlled HTTP fixture.

Live provider calls are intentionally not part of automated tests because they require secrets, incur cost, and are nondeterministic.
