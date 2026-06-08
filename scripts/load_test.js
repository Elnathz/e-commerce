import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 50 }, // Ramp up to 50 users
    { duration: '1m', target: 100 }, // Ramp up to 100 users
    { duration: '30s', target: 250 }, // Ramp up to 250 users
    { duration: '30s', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
    http_req_failed: ['rate<0.01'],   // Error rate < 1%
  },
};

const BASE_URL = 'http://localhost:8000';

export default function () {
  // Simulate 1. Checkout (POST)
  const checkoutPayload = JSON.stringify({
    items: [{ product_variant_id: 1, quantity: 1 }],
    payment_method: 'tripay',
    courier: 'jne'
  });
  const params = { headers: { 'Content-Type': 'application/json' } };
  let checkoutRes = http.post(`${BASE_URL}/api/checkout`, checkoutPayload, params);
  
  check(checkoutRes, {
    'checkout status is 200 or 201': (r) => r.status === 200 || r.status === 201,
  });

  sleep(1);

  // Simulate 2. Analytics (GET) - Heavy read
  let analyticsRes = http.get(`${BASE_URL}/admin/dashboard/revenue`);
  check(analyticsRes, {
    'analytics status is 200 or 403': (r) => r.status === 200 || r.status === 403,
  });

  sleep(1);
}
