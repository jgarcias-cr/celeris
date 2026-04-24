# Celeris API + React SPA: Basic Username/Password Auth

This is a minimal example using:
- Celeris API stub
- `POST /api/auth/login` to exchange username/password for JWT
- `Authorization: Bearer <token>` from React for protected API calls

## 1. Backend setup

In `packages/api-stub/.env`, set:

```dotenv
SECURITY_AUTH_JWT_ENABLED=true
JWT_SECRET=change-this-to-a-long-random-secret
JWT_TTL_SECONDS=3600

DEMO_AUTH_USERNAME=demo
# bcrypt for "password123"
DEMO_AUTH_PASSWORD_HASH=$2y$12$9GK/vlsIdSj5KeClYZyTu.ZDgGUdh0PsyNprCZsHo37ykuPQ4AWfS
```

The example includes:
- Login endpoint: `POST /api/auth/login`
- Authenticated endpoint: `GET /api/auth/me`
- Protected contacts endpoints (`ContactController` has `#[Authorize]`)

## 2. Login request

```bash
curl -X POST http://localhost/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"demo","password":"password123"}'
```

Successful response:

```json
{
  "token_type": "Bearer",
  "access_token": "<jwt>",
  "expires_in": 3600,
  "user": {
    "username": "demo",
    "roles": ["user"],
    "permissions": ["contacts:read", "contacts:write"]
  }
}
```

## 3. Call protected API

```bash
curl http://localhost/api/auth/me \
  -H "Authorization: Bearer <jwt>"
```

```bash
curl http://localhost/api/contacts \
  -H "Authorization: Bearer <jwt>"
```

## 4. React SPA example

```tsx
import { useState } from 'react';

type AuthPayload = {
  token_type: string;
  access_token: string;
  expires_in: number;
  user: { username: string };
};

export default function App() {
  const [username, setUsername] = useState('demo');
  const [password, setPassword] = useState('password123');
  const [token, setToken] = useState<string | null>(null);
  const [result, setResult] = useState<string>('');

  async function login() {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    });

    const data = (await res.json()) as AuthPayload | { error: string; message?: string };
    if (!res.ok || !('access_token' in data)) {
      setResult(JSON.stringify(data, null, 2));
      return;
    }

    setToken(data.access_token);
    setResult(`Logged in as ${data.user.username}`);
  }

  async function loadContacts() {
    if (!token) return;

    const res = await fetch('/api/contacts', {
      headers: { Authorization: `Bearer ${token}` },
    });

    const data = await res.json();
    setResult(JSON.stringify(data, null, 2));
  }

  return (
    <main style={{ padding: 16 }}>
      <h1>Celeris + React Auth Demo</h1>
      <input value={username} onChange={(e) => setUsername(e.target.value)} placeholder="username" />
      <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" placeholder="password" />
      <button onClick={login}>Login</button>
      <button onClick={loadContacts} disabled={!token}>Load Contacts</button>
      <pre>{result}</pre>
    </main>
  );
}
```

## 5. Axios equivalent (modular structure)

Install Axios:

```bash
npm install axios
```

Suggested frontend files:
- `src/api/tokenStore.ts`
- `src/api/apiClient.ts`
- `src/api/authService.ts`
- `src/api/contactService.ts`
- `src/App.tsx`

`src/api/tokenStore.ts`:

```ts
const ACCESS_TOKEN_KEY = 'access_token';

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_KEY);
}

export function setAccessToken(token: string): void {
  localStorage.setItem(ACCESS_TOKEN_KEY, token);
}

export function clearAccessToken(): void {
  localStorage.removeItem(ACCESS_TOKEN_KEY);
}
```

`src/api/apiClient.ts`:

```ts
import axios, { AxiosHeaders } from 'axios';
import { clearAccessToken, getAccessToken } from './tokenStore';

export const api = axios.create({ baseURL: '/api' });

api.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (!token) {
    return config;
  }

  if (config.headers instanceof AxiosHeaders) {
    config.headers.set('Authorization', `Bearer ${token}`);
  } else {
    config.headers = { ...(config.headers ?? {}), Authorization: `Bearer ${token}` };
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearAccessToken();
    }
    return Promise.reject(error);
  },
);
```

`src/api/authService.ts`:

```ts
import { api } from './apiClient';
import { clearAccessToken, setAccessToken } from './tokenStore';

export type LoginPayload = {
  username: string;
  password: string;
};

export type LoginResponse = {
  token_type: string;
  access_token: string;
  expires_in: number;
  user: { username: string; roles: string[]; permissions: string[] };
};

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/auth/login', payload);
  setAccessToken(data.access_token);
  return data;
}

export async function me(): Promise<unknown> {
  const { data } = await api.get('/auth/me');
  return data;
}

export function logout(): void {
  clearAccessToken();
}
```

`src/api/contactService.ts`:

```ts
import { api } from './apiClient';

export async function listContacts(): Promise<unknown> {
  const { data } = await api.get('/contacts');
  return data;
}
```

`src/App.tsx`:

```tsx
import { useState } from 'react';
import { login, logout } from './api/authService';
import { listContacts } from './api/contactService';

export default function App() {
  const [username, setUsername] = useState('demo');
  const [password, setPassword] = useState('password123');
  const [result, setResult] = useState('');

  async function onLogin() {
    try {
      const data = await login({ username, password });
      setResult(`Logged in as ${data.user.username}`);
    } catch (error) {
      setResult(`Login failed: ${String(error)}`);
    }
  }

  async function onLoadContacts() {
    try {
      const data = await listContacts();
      setResult(JSON.stringify(data, null, 2));
    } catch (error) {
      setResult(`Contacts request failed: ${String(error)}`);
    }
  }

  function onLogout() {
    logout();
    setResult('Logged out');
  }

  return (
    <main style={{ padding: 16 }}>
      <h1>Celeris + React Auth Demo (Axios)</h1>
      <input value={username} onChange={(e) => setUsername(e.target.value)} placeholder="username" />
      <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" placeholder="password" />
      <button onClick={onLogin}>Login</button>
      <button onClick={onLoadContacts}>Load Contacts</button>
      <button onClick={onLogout}>Logout</button>
      <pre>{result}</pre>
    </main>
  );
}
```
