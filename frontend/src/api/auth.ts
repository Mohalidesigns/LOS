import { api, ensureCsrfCookie, unwrap } from './client';
import type { components } from './schema';

export type LoginResult = components['schemas']['LoginResult'];
export type LoginStatus = LoginResult['status'];
export type Me = components['schemas']['Me'];
export type EffectiveAccess = components['schemas']['EffectiveAccess'];
export type Scope = components['schemas']['Scope'];

export const authApi = {
  csrf: ensureCsrfCookie,

  async login(email: string, password: string): Promise<LoginResult> {
    await ensureCsrfCookie();
    return unwrap(await api.POST('/api/v1/auth/login', { body: { email, password } })).data;
  },

  async verifyMfa(code: string): Promise<LoginResult> {
    return unwrap(await api.POST('/api/v1/auth/mfa/verify', { body: { code } })).data;
  },

  async stepUp(password: string, code: string | null): Promise<{ step_up_ref: string; valid_for_minutes: number }> {
    return unwrap(await api.POST('/api/v1/auth/step-up', { body: { password, code } })).data;
  },

  async logout(): Promise<void> {
    await api.POST('/api/v1/auth/logout');
  },

  async me(): Promise<Me> {
    return unwrap(await api.GET('/api/v1/me')).data;
  },

  async effectiveAccess(): Promise<EffectiveAccess> {
    return unwrap(await api.GET('/api/v1/me/effective-access')).data;
  },
};
