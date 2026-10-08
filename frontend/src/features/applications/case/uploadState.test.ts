import { describe, expect, it } from 'vitest';
import type { DocumentRecord } from '@/api/lending';
import { initialUpload, uploadAnnouncement, uploadReducer } from './uploadState';

const SHA = 'a'.repeat(64);
function doc(scan: 'clean' | 'infected', duplicates: DocumentRecord['versions'][number]['duplicates'] = []): DocumentRecord {
  const v = { id: 'v1', version_no: 1, filename: 'statement.pdf', mime_type: 'application/pdf', size_bytes: 2048, sha256: SHA, scan_status: scan, scan_signature: scan === 'infected' ? 'Eicar-Test-Signature' : null, scanner: 'sim', uploaded_by: 'u1', uploaded_at: '2026-10-08T10:00:00Z', duplicates };
  return { id: 'd1', application_id: 'a1', checklist_item_id: 'c1', party_id: null, document_type: 'STATEMENT_6M', title: 'Statement', latest_version: v, versions: [v], created_at: '2026-10-08T10:00:00Z' };
}

describe('uploadReducer', () => {
  it('goes idle → uploading → clean with hash and duplicates', () => {
    let s = uploadReducer(initialUpload, { type: 'start', fileName: 'statement.pdf', size: 2048 });
    expect(s.phase).toBe('uploading');
    expect(uploadAnnouncement(s)).toMatch(/Uploading statement\.pdf/);
    s = uploadReducer(s, { type: 'success', document: doc('clean', [{ document_id: 'x', application_id: 'b', application_reference: 'DEMO-2026-000009', same_application: false }]) });
    expect(s).toMatchObject({ phase: 'clean', sha256: SHA, size: 2048, duplicates: [{ application_reference: 'DEMO-2026-000009', same_application: false }] });
    expect(uploadAnnouncement(s)).toBe('statement.pdf uploaded and scanned clean.');
  });

  it('marks a quarantined upload as infected (never readable)', () => {
    const s = uploadReducer(uploadReducer(initialUpload, { type: 'start', fileName: 'x.pdf', size: 1 }), { type: 'success', document: doc('infected') });
    expect(s.phase).toBe('infected');
    expect(s.scanSignature).toBe('Eicar-Test-Signature');
    expect(uploadAnnouncement(s)).toMatch(/quarantined/);
  });

  it('records failures with the correlation id and resets', () => {
    let s = uploadReducer(initialUpload, { type: 'start', fileName: 'x.pdf', size: 1 });
    s = uploadReducer(s, { type: 'failure', message: 'File too large', correlationId: 'corr-9' });
    expect(s).toMatchObject({ phase: 'error', error: 'File too large', correlationId: 'corr-9' });
    expect(uploadReducer(s, { type: 'reset' })).toEqual(initialUpload);
  });

  it('ignores a second start while uploading and stray results when idle', () => {
    const uploading = uploadReducer(initialUpload, { type: 'start', fileName: 'a.pdf', size: 1 });
    expect(uploadReducer(uploading, { type: 'start', fileName: 'b.pdf', size: 2 })).toBe(uploading);
    expect(uploadReducer(initialUpload, { type: 'success', document: doc('clean') })).toBe(initialUpload);
    expect(uploadReducer(initialUpload, { type: 'failure', message: 'x' })).toBe(initialUpload);
  });

  it('errors when the server returns no version', () => {
    const d = { ...doc('clean'), latest_version: null, versions: [] };
    const s = uploadReducer(uploadReducer(initialUpload, { type: 'start', fileName: 'a', size: 1 }), { type: 'success', document: d });
    expect(s.phase).toBe('error');
  });
});
