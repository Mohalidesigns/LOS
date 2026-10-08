/** Only same-origin app paths are allowed as post-login targets (no open redirect). */
export function safeNext(raw: string | null | undefined): string {
  if (!raw) return '/';
  if (!raw.startsWith('/') || raw.startsWith('//') || raw.startsWith('/\\') || raw.startsWith('/login')) return '/';
  return raw;
}
