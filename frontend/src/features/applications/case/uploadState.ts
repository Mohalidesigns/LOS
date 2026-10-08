/**
 * Per-checklist-item upload state machine (pure, unit-tested):
 *   idle → uploading → clean | infected | error;  any → idle on reset.
 * "infected" means the server quarantined the file: it is never readable.
 */
import type { DocumentRecord } from '@/api/lending';

export type UploadPhase = 'idle' | 'uploading' | 'clean' | 'infected' | 'error';

export type UploadState = {
  phase: UploadPhase;
  fileName: string | null;
  size: number | null;
  sha256: string | null;
  scanSignature: string | null;
  duplicates: { application_reference: string; same_application: boolean }[];
  error: string | null;
  correlationId: string | null;
};

export type UploadEvent =
  | { type: 'start'; fileName: string; size: number }
  | { type: 'success'; document: DocumentRecord }
  | { type: 'failure'; message: string; correlationId?: string | null }
  | { type: 'reset' };

export const initialUpload: UploadState = { phase: 'idle', fileName: null, size: null, sha256: null, scanSignature: null, duplicates: [], error: null, correlationId: null };

export function uploadReducer(state: UploadState, event: UploadEvent): UploadState {
  switch (event.type) {
    case 'start':
      if (state.phase === 'uploading') return state; // one upload at a time per item
      return { ...initialUpload, phase: 'uploading', fileName: event.fileName, size: event.size };
    case 'success': {
      if (state.phase !== 'uploading') return state;
      const v = event.document.latest_version ?? event.document.versions[event.document.versions.length - 1] ?? null;
      if (!v) return { ...state, phase: 'error', error: 'The server stored no version for this upload.' };
      return {
        ...state,
        phase: v.scan_status === 'infected' ? 'infected' : 'clean',
        fileName: v.filename,
        size: v.size_bytes,
        sha256: v.sha256,
        scanSignature: v.scan_signature,
        duplicates: v.duplicates.map((d) => ({ application_reference: d.application_reference, same_application: d.same_application })),
      };
    }
    case 'failure':
      if (state.phase !== 'uploading') return state;
      return { ...state, phase: 'error', error: event.message, correlationId: event.correlationId ?? null };
    case 'reset':
      return initialUpload;
  }
}

/** Polite live-region text for each phase. */
export function uploadAnnouncement(s: UploadState): string {
  switch (s.phase) {
    case 'idle':
      return '';
    case 'uploading':
      return `Uploading ${s.fileName ?? 'file'}. The file is hashed and scanned before anyone can open it.`;
    case 'clean':
      return `${s.fileName ?? 'File'} uploaded and scanned clean.`;
    case 'infected':
      return `${s.fileName ?? 'File'} was quarantined: malware detected. It can never be opened.`;
    case 'error':
      return `Upload failed: ${s.error ?? 'unknown error'}`;
  }
}
