/** Hand a Blob to the browser as a file download via a short-lived object URL (nothing is persisted). */
export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.rel = 'noopener';
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

/** First 8 and last 4 hex characters of a SHA-256, for display ("3f2a91bc…9e01"). */
export function shortHash(sha256: string): string {
  return sha256.length > 12 ? `${sha256.slice(0, 8)}…${sha256.slice(-4)}` : sha256;
}
