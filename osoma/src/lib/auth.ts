import { OIDC_CLIENT_ID, OIDC_SERVER_URL, OIDC_SERVER_URL_INTERNAL } from '@/config/keycloak.ts';
import { getAuthMode } from '@/lib/mode.ts';
import { getOrganizationRights, normalizeOrganizationRole, type OrganizationRole } from '@/lib/rbac.ts';

type OrganizationRights = ReturnType<typeof getOrganizationRights>

type SessionUser = {
  name?: string;
  email?: string;
  sub: string;
  accountKey?: string;
  accountName?: string;
  organizationRole?: OrganizationRole;
  rights?: OrganizationRights;
  isSuperAdmin?: boolean;
}

export interface AuthSession {
  accessToken: string;
  idToken: string;
  refreshToken: string;
  expiresAt: number;
  user?: SessionUser;
}

const STORAGE_KEY = 'metadatapp_auth_session';
const BYPASS_ORGANIZATION_ROLE: OrganizationRole = 'admin';

export class AuthService {
  private static instance: AuthService;
  private session: AuthSession | null = null;
  private listeners: Array<(session: AuthSession | null) => void> = [];

  private constructor() {
    this.loadSession();
  }

  static getInstance(): AuthService {
    if (!AuthService.instance) {
      AuthService.instance = new AuthService();
    }
    return AuthService.instance;
  }

  subscribe(listener: (session: AuthSession | null) => void): () => void {
    this.listeners.push(listener);
    return () => {
      this.listeners = this.listeners.filter((l) => l !== listener);
    };
  }

  private notify(): void {
    this.listeners.forEach((l) => l(this.session));
  }

  private isAuthBypassed(): boolean {
    return getAuthMode() === 'bypass';
  }

  private loadSession(): void {
    if (this.isAuthBypassed()) {
      // In mock mode, always provide a mock authenticated session
      this.session = {
        accessToken: 'mock-access-token',
        idToken: 'mock-id-token',
        refreshToken: 'mock-refresh-token',
        expiresAt: Math.floor(Date.now() / 1000) + 3600, // 1 hour from now
        user: {
          name: 'Mock User',
          email: 'mock@example.com',
          sub: 'mock-user-id',
          accountKey: 'mock-account',
          accountName: 'Mock Organization',
          organizationRole: BYPASS_ORGANIZATION_ROLE,
          rights: getOrganizationRights(BYPASS_ORGANIZATION_ROLE),
          isSuperAdmin: true,
        },
      };
      this.notify();
      return;
    }

    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const storedData = JSON.parse(stored) as Record<string, unknown>;
        // Attempt to cast to AuthSession, but handle potential type mismatches
        // This assumes the structure of storedData matches AuthSession
        this.session = storedData as unknown as AuthSession;
        // Check if token is expired
        if (this.isExpired()) {
          this.refreshToken().catch(() => {
            this.clearSession();
          });
        }
      }
    } catch (error) {
      console.error('Failed to load auth session:', error);
      this.clearSession();
    }
  }

  private saveSession(): void {
    if (this.isAuthBypassed()) {
      return; // Don't save in mock mode
    }

    if (this.session) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.session));
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
    this.notify();
  }

  private isExpired(): boolean {
    return !this.session || Date.now() >= this.session.expiresAt * 1000;
  }

  async login(): Promise<void> {
    if (this.isAuthBypassed()) {
      // In mock mode, just reload the session
      this.loadSession();
      return;
    }

    const state = this.generateState();
    const codeVerifier = this.generateCodeVerifier();
    const codeChallenge = await this.generateCodeChallenge(codeVerifier);

    // Store code verifier for later
    sessionStorage.setItem('pkce_code_verifier', codeVerifier);
    sessionStorage.setItem('pkce_state', state);

    const authUrl = new URL(`${OIDC_SERVER_URL}/protocol/openid-connect/auth`);
    authUrl.searchParams.set('client_id', OIDC_CLIENT_ID);
    authUrl.searchParams.set('response_type', 'code');
    authUrl.searchParams.set('scope', 'openid profile email');
    authUrl.searchParams.set('redirect_uri', window.location.origin + '/auth/callback');
    authUrl.searchParams.set('state', state);
    authUrl.searchParams.set('code_challenge', codeChallenge);
    authUrl.searchParams.set('code_challenge_method', 'S256');

    window.location.href = authUrl.toString();
  }

  async handleCallback(code: string, state: string): Promise<void> {
    if (this.isAuthBypassed()) {
      return; // Should not happen in mock mode
    }

    const storedState = sessionStorage.getItem('pkce_state');
    const codeVerifier = sessionStorage.getItem('pkce_code_verifier');

    if (!storedState || !codeVerifier || state !== storedState) {
      throw new Error('Invalid state or missing PKCE verifier');
    }

    // Clean up
    sessionStorage.removeItem('pkce_state');
    sessionStorage.removeItem('pkce_code_verifier');

    const tokenResponse = await fetch(`${OIDC_SERVER_URL_INTERNAL}/protocol/openid-connect/token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        client_id: OIDC_CLIENT_ID,
        grant_type: 'authorization_code',
        code,
        redirect_uri: window.location.origin + '/auth/callback',
        code_verifier: codeVerifier,
      }),
    });

    if (!tokenResponse.ok) {
      throw new Error('Failed to exchange code for tokens');
    }

    const tokens = await tokenResponse.json();
    await this.setSessionFromTokens(tokens);
  }

  private decodeJwtPayload(token: string): Record<string, unknown> {
    try {
      const payloadSegment = token.split('.')[1];
      if (!payloadSegment) {
        throw new Error('Missing JWT payload');
      }

      const normalized = payloadSegment.replace(/-/g, '+').replace(/_/g, '/');
      const padded = normalized.padEnd(normalized.length + ((4 - (normalized.length % 4)) % 4), '=');
      const decoded = atob(padded);
      const bytes = Uint8Array.from(decoded, (character) => character.charCodeAt(0));

      return JSON.parse(new TextDecoder().decode(bytes)) as Record<string, unknown>;
    } catch {
      throw new Error('Failed to decode JWT payload');
    }
  }

  private resolveRealmRoles(payloads: Record<string, unknown>[]): string[] {
    for (const payload of payloads) {
      const realmAccess = payload.realm_access
      if (!realmAccess || typeof realmAccess !== 'object') {
        continue
      }

      const roles = (realmAccess as { roles?: unknown }).roles
      if (Array.isArray(roles)) {
        return roles.filter((role): role is string => typeof role === 'string')
      }
    }

    return []
  }

  private buildSessionUser(payload: Record<string, unknown>, accessPayload?: Record<string, unknown>): SessionUser {
    const subject = typeof payload.sub === 'string' && payload.sub.length > 0 ? payload.sub : null;
    if (!subject) {
      throw new Error('Missing required subject claim in JWT payload');
    }

    let displayName = 'Unknown User';
    if (typeof payload.name === 'string' && payload.name.length > 0) {
      displayName = payload.name;
    } else if (typeof payload.preferred_username === 'string' && payload.preferred_username.length > 0) {
      displayName = payload.preferred_username;
    }
    const realmRoles = this.resolveRealmRoles([payload, accessPayload ?? {}]).map((role) => role.toLowerCase())
    const isSuperAdmin = realmRoles.includes('super_admin')
    const organizationRole =
      normalizeOrganizationRole(payload.organization_role) ??
      normalizeOrganizationRole(accessPayload?.organization_role) ??
      (isSuperAdmin ? 'admin' : undefined)

    return {
      name: displayName,
      email: typeof payload.email === 'string' ? payload.email : undefined,
      sub: subject,
      accountKey: typeof payload.account_key === 'string' ? payload.account_key : undefined,
      accountName: typeof payload.account_name === 'string' ? payload.account_name : undefined,
      organizationRole,
      rights: organizationRole ? getOrganizationRights(organizationRole) : undefined,
      isSuperAdmin,
    };
  }

  private async setSessionFromTokens(tokensObj: unknown): Promise<void> {
    const tokens = tokensObj as Record<string, unknown>;
    // Refresh responses may not always include a new id_token.
    // Keep the previous one so logout can still send id_token_hint.
    const rawIdToken = tokens.id_token;
    const idToken = typeof rawIdToken === 'string' && rawIdToken.length > 0
      ? rawIdToken
      : this.session?.idToken;

    if (!idToken) {
      throw new Error('Missing id_token in authentication response');
    }

    // Decode ID token to get user info
    const payload = this.decodeJwtPayload(idToken);
    let accessPayload: Record<string, unknown> | undefined
    if (typeof tokens.access_token === 'string' && tokens.access_token.length > 0) {
      try {
        accessPayload = this.decodeJwtPayload(tokens.access_token)
      } catch {
        accessPayload = undefined
      }
    }

    this.session = {
      accessToken: String(tokens.access_token),
      idToken: idToken,
      refreshToken: String(tokens.refresh_token),
      expiresAt: Math.floor(Date.now() / 1000) + Number(tokens.expires_in),
      user: this.buildSessionUser(payload, accessPayload),
    };

    this.saveSession();
  }

  async refreshToken(): Promise<void> {
    if (this.isAuthBypassed()) {
      // In mock mode, just extend the session
      if (this.session) {
        this.session.expiresAt = Math.floor(Date.now() / 1000) + 3600;
      }
      return;
    }

    if (!this.session?.refreshToken) {
      throw new Error('No refresh token available');
    }

    const response = await fetch(`${OIDC_SERVER_URL_INTERNAL}/protocol/openid-connect/token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        client_id: OIDC_CLIENT_ID,
        grant_type: 'refresh_token',
        refresh_token: this.session.refreshToken,
      }),
    });

    if (!response.ok) {
      throw new Error('Failed to refresh token');
    }

    const tokens = await response.json();
    await this.setSessionFromTokens(tokens);
  }

  logout(): void {
    if (this.isAuthBypassed()) {
      // In mock mode, just clear the session
      this.clearSession();
      return;
    }

    const logoutUrl = new URL(`${OIDC_SERVER_URL}/protocol/openid-connect/logout`);
    // Keycloak 22+ requires client_id when using post_logout_redirect_uri,
    // and id_token_hint when available.
    logoutUrl.searchParams.set('client_id', OIDC_CLIENT_ID);
    if (this.session?.idToken) {
      logoutUrl.searchParams.set('id_token_hint', this.session.idToken);
    }
    logoutUrl.searchParams.set('post_logout_redirect_uri', window.location.origin);
    this.clearSession();
    window.location.href = logoutUrl.toString();
  }

  private clearSession(): void {
    this.session = null;
    if (!this.isAuthBypassed()) {
      localStorage.removeItem(STORAGE_KEY);
    }
    this.notify();
  }

  getSession(): AuthSession | null {
    return this.session;
  }

  isAuthenticated(): boolean {
    return this.session !== null && (!this.isExpired() || this.isAuthBypassed());
  }

  getAccessToken(): string | null {
    return this.session?.accessToken || null;
  }

  private generateState(): string {
    return this.generateRandomString(32);
  }

  private generateCodeVerifier(): string {
    return this.generateRandomString(64);
  }

  private generateRandomString(length: number): string {
    const array = new Uint8Array(length);
    crypto.getRandomValues(array);
    return btoa(String.fromCharCode(...array))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '');
  }

  private async generateCodeChallenge(codeVerifier: string): Promise<string> {
    const encoder = new TextEncoder();
    const data = encoder.encode(codeVerifier);
    const digest = await crypto.subtle.digest('SHA-256', data);
    return btoa(String.fromCharCode(...new Uint8Array(digest)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=/g, '');
  }
}
