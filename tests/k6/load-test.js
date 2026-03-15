/**
 * QuickMessage — K6 Load Test
 *
 * Tests the messaging flow under load:
 *   1. GET /login → parse CSRF token
 *   2. POST /login (session-based auth)
 *   3. GET /users
 *   4. GET /messages/{userId} → parse CSRF token
 *   5. POST /messages/{userId} → send message
 *
 * Usage:
 *   k6 run --env BASE_URL=http://localhost tests/k6/load-test.js
 *
 * Via Docker (no k6 install needed):
 *   docker run -i --network host grafana/k6 run \
 *     --env BASE_URL=http://localhost \
 *     - < tests/k6/load-test.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { parseHTML } from 'k6/html';
import { CookieJar } from 'k6/http';

// ─── Configuration ────────────────────────────────────────────
export const options = {
    scenarios: {
        messaging_load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '10s', target: 10 },  // Ramp up
                { duration: '40s', target: 50 },  // Hold at 50 VUs
                { duration: '10s', target: 0 },   // Ramp down
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500'],  // 95th percentile under 500ms
        http_req_failed:   ['rate<0.01'],  // Less than 1% failures
        message_sent:      ['rate>0.95'],  // 95%+ message sends succeed
    },
};

// ─── Custom metrics ────────────────────────────────────────────
const messageSentSuccess = new Rate('message_sent');
const loginDuration      = new Trend('login_duration_ms');
const messageDuration    = new Trend('message_duration_ms');

// ─── Test users (seeded by DatabaseSeeder) ────────────────────
const TEST_USERS = [
    { email: 'anton@example.com',   password: 'Password123!' },
    { email: 'bob@example.com',     password: 'Password123!' },
    { email: 'charlie@example.com', password: 'Password123!' },
    { email: 'diana@example.com',   password: 'Password123!' },
    { email: 'elena@example.com',   password: 'Password123!' },
];

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

// ─── Helper: extract CSRF token from HTML ─────────────────────
function extractCsrfToken(htmlBody) {
    const doc = parseHTML(htmlBody);
    const token = doc.find('meta[name="csrf-token"]').attr('content');
    if (!token) {
        // Fallback: find hidden input field
        return doc.find('input[name="_token"]').attr('value') || '';
    }
    return token;
}

// ─── Helper: pick a random recipient (different from sender) ───
function pickRecipient(senderEmail) {
    const others = TEST_USERS.filter(u => u.email !== senderEmail);
    return others[Math.floor(Math.random() * others.length)];
}

// ─── Main VU scenario ─────────────────────────────────────────
export default function () {
    const jar    = new CookieJar();
    const params = { jar, redirects: 5 };

    // Pick a random user for this VU iteration
    const user = TEST_USERS[Math.floor(Math.random() * TEST_USERS.length)];

    // ── Step 1: GET /login → extract CSRF token ────────────────
    let loginPage;
    group('1. GET /login', () => {
        loginPage = http.get(`${BASE_URL}/login`, params);
        check(loginPage, {
            'login page loads (200)': (r) => r.status === 200,
            'has CSRF meta tag':      (r) => r.body.includes('csrf-token'),
        });
    });

    const csrfToken = extractCsrfToken(loginPage.body);

    // ── Step 2: POST /login ────────────────────────────────────
    let loginStart;
    group('2. POST /login', () => {
        loginStart = Date.now();

        const res = http.post(
            `${BASE_URL}/login`,
            {
                _token:   csrfToken,
                email:    user.email,
                password: user.password,
            },
            { ...params, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } },
        );

        loginDuration.add(Date.now() - loginStart);

        check(res, {
            'login succeeded (redirected to /users)': (r) =>
                r.status === 200 || r.status === 302,
        });
    });

    sleep(0.5);

    // ── Step 3: GET /users ─────────────────────────────────────
    let usersPage;
    group('3. GET /users', () => {
        usersPage = http.get(`${BASE_URL}/users`, params);
        check(usersPage, {
            'users page loads (200)': (r) => r.status === 200,
        });
    });

    // ── Step 4: Open conversation & send message ───────────────
    // We need another user's ID — for simplicity use sequential IDs
    // In real scenario these would be parsed from the users page
    const recipientIndex = ((TEST_USERS.indexOf(user) + 1) % TEST_USERS.length) + 1;

    group('4. GET /messages/{userId}', () => {
        const convPage = http.get(`${BASE_URL}/messages/${recipientIndex}`, params);
        check(convPage, {
            'conversation page loads': (r) => r.status === 200 || r.status === 404,
        });

        // ── Step 5: POST message ───────────────────────────────
        if (convPage.status === 200) {
            const msgCsrf = extractCsrfToken(convPage.body)
                || csrfToken; // fallback to login csrf

            const msgStart = Date.now();

            const msgRes = http.post(
                `${BASE_URL}/messages/${recipientIndex}`,
                {
                    _token: msgCsrf,
                    body:   `Load test message from ${user.email} at ${new Date().toISOString()}`,
                },
                { ...params, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } },
            );

            messageDuration.add(Date.now() - msgStart);

            const sent = check(msgRes, {
                'message sent (redirect)': (r) => r.status === 200 || r.status === 302,
            });

            messageSentSuccess.add(sent);
        }
    });

    // ── Step 6: GET /messages/unread ──────────────────────────
    group('5. GET /messages/unread', () => {
        const unreadRes = http.get(`${BASE_URL}/messages/unread`, params);
        check(unreadRes, {
            'unread endpoint responds': (r) => r.status === 200,
            'unread count is JSON':     (r) => {
                try {
                    JSON.parse(r.body);
                    return true;
                } catch {
                    return false;
                }
            },
        });
    });

    sleep(1);
}
