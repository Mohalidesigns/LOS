/**
 * Typed adapters for the P1 lending endpoints (products, parties,
 * applications, KYC/screening, documents). Types come only from the
 * generated contract (schema.d.ts); this file names them and unwraps
 * `{ data }`. Application reads and mutations also return the ETag so the
 * next mutation can send If-Match.
 */
import { api, etagOf, formBody, idempotencyKey, unwrap } from './client';
import type { components, operations } from './schema';

type S = components['schemas'];

export type Money = S['Money'];
export type PageMeta = S['PageMeta'];
export type ProductSummary = S['ProductSummary'];
export type Product = S['Product'];
export type PartySummary = S['PartySummary'];
export type Party = S['Party'];
export type PartyIdentity = Party['identities'][number];
export type PartyCreate = S['PartyCreate'];
export type PartyMatch = S['PartyMatch'];
export type PartyRelationship = S['PartyRelationship'];
export type BeneficialOwner = S['BeneficialOwner'];
export type ApplicationSummary = S['ApplicationSummary'];
export type Application = S['Application'];
export type ApplicationStatus = ApplicationSummary['status'];
export type Applicant = Application['applicants'][number];
export type ApplicationCreate = S['ApplicationCreate'];
export type ApplicationEvent = S['ApplicationEvent'];
export type ScreeningAlert = S['ScreeningAlert'];
export type KycStatus = S['KycStatus'];
export type DocumentRecord = S['Document'];
export type DocumentVersion = DocumentRecord['versions'][number];
export type ChecklistItem = S['ChecklistItem'];
export type Checklist = S['Checklist'];
export type ChangeRequest = S['ChangeRequest'];
export type LegalEntity = S['LegalEntity'];
export type OrgUnit = S['OrgUnit'];

type Json<O extends keyof operations, C extends number> = operations[O]['responses'] extends Record<C, { content: { 'application/json': infer B } }> ? B : never;
type Body<O extends keyof operations> = operations[O]['requestBody'] extends { content: { 'application/json': infer B } } | undefined ? B : never;

export type ApplicationAction = operations['actOnApplication']['parameters']['path']['action'];
export type ActionBody = Body<'actOnApplication'>;
export type AmendBody = Body<'amendApplication'>;
export type ApplicantRole = Body<'addApplicant'>['role'];
export type RelationshipRole = Body<'addPartyRelationship'>['role'];
export type ConsentBody = Body<'recordPartyConsent'>;
export type Consents = Json<'listPartyConsents', 200>['data'];
export type IdentityType = operations['verifyPartyIdentity']['parameters']['path']['type'];
export type AlertStep = operations['dispositionScreeningAlert']['parameters']['path']['step'];
export type AlertDispositionBody = Body<'dispositionScreeningAlert'>;
export type ChecklistAction = operations['actOnChecklistItem']['parameters']['path']['action'];
export type ChecklistActionBody = Body<'actOnChecklistItem'>;
export type CreatedParty = Json<'createParty', 201>['data'];
export type AsAt = Json<'applicationAsAt', 200>['data'];
export type ApplicationStats = Json<'applicationStats', 200>['data'];

export type Page<T> = { data: T[]; meta: PageMeta };
export type WithEtag<T> = { data: T; etag: string };

export type ApplicationFilters = {
  status?: readonly string[];
  open?: boolean;
  mine?: boolean;
  q?: string;
  productKey?: string;
  partyId?: string;
  size?: number;
  after?: string | null;
};

const idem = () => ({ 'Idempotency-Key': idempotencyKey() });

export const productsApi = {
  async list(): Promise<ProductSummary[]> {
    return unwrap(await api.GET('/api/v1/products')).data;
  },
  async get(key: string): Promise<Product> {
    return unwrap(await api.GET('/api/v1/products/{key}', { params: { path: { key } } })).data;
  },
};

export const orgApi = {
  async legalEntities(): Promise<LegalEntity[]> {
    return unwrap(await api.GET('/api/v1/legal-entities', { params: { query: { 'page[size]': 100 } } })).data;
  },
  async orgUnits(legalEntityId: string): Promise<OrgUnit[]> {
    return unwrap(await api.GET('/api/v1/org-units', { params: { query: { 'page[size]': 100, 'filter[legal_entity_id]': legalEntityId } } })).data;
  },
};

export const partiesApi = {
  async list(params: { q?: string; type?: string; after?: string | null; size?: number } = {}): Promise<Page<PartySummary>> {
    const query: operations['listParties']['parameters']['query'] = { 'page[size]': params.size ?? 25 };
    if (params.q) query['filter[q]'] = params.q;
    if (params.type) query['filter[type]'] = params.type;
    if (params.after) query['page[after]'] = params.after;
    return unwrap(await api.GET('/api/v1/parties', { params: { query } }));
  },
  async get(id: string): Promise<Party> {
    return unwrap(await api.GET('/api/v1/parties/{id}', { params: { path: { id } } })).data;
  },
  async create(body: PartyCreate, key: string = idempotencyKey()): Promise<CreatedParty> {
    return unwrap(await api.POST('/api/v1/parties', { params: { header: { 'Idempotency-Key': key } }, body })).data;
  },
  async match(body: Body<'matchParties'>): Promise<PartyMatch[]> {
    return unwrap(await api.POST('/api/v1/parties/actions/match', { body })).data;
  },
  async relationships(id: string): Promise<{ relationships: PartyRelationship[]; beneficial_owners: BeneficialOwner[] }> {
    return unwrap(await api.GET('/api/v1/parties/{id}/relationships', { params: { path: { id } } })).data;
  },
  async addRelationship(id: string, body: Body<'addPartyRelationship'>): Promise<PartyRelationship> {
    return unwrap(await api.POST('/api/v1/parties/{id}/relationships', { params: { path: { id }, header: idem() }, body })).data;
  },
  async consents(id: string): Promise<Consents> {
    return unwrap(await api.GET('/api/v1/parties/{id}/consents', { params: { path: { id } } })).data;
  },
  async recordConsent(id: string, body: ConsentBody, key: string = idempotencyKey()): Promise<Consents> {
    return unwrap(await api.POST('/api/v1/parties/{id}/consents', { params: { path: { id }, header: { 'Idempotency-Key': key } }, body })).data;
  },
  async verifyIdentity(id: string, type: IdentityType): Promise<Party> {
    return unwrap(await api.POST('/api/v1/parties/{id}/identities/{type}/actions/verify', { params: { path: { id, type }, header: idem() } })).data;
  },
};

export const applicationsApi = {
  async list(f: ApplicationFilters = {}): Promise<Page<ApplicationSummary>> {
    const query: operations['listApplications']['parameters']['query'] = { 'page[size]': f.size ?? 25 };
    if (f.status && f.status.length > 0) query['filter[status]'] = f.status.join(',');
    if (f.open) query['filter[open]'] = 'true';
    if (f.mine) query['filter[mine]'] = 'true';
    if (f.q) query['filter[q]'] = f.q;
    if (f.productKey) query['filter[product_key]'] = f.productKey;
    if (f.partyId) query['filter[party_id]'] = f.partyId;
    if (f.after) query['page[after]'] = f.after;
    return unwrap(await api.GET('/api/v1/applications', { params: { query } }));
  },
  async stats(): Promise<ApplicationStats> {
    return unwrap(await api.GET('/api/v1/applications/stats')).data;
  },
  async create(body: ApplicationCreate, key: string): Promise<WithEtag<Application>> {
    const r = await api.POST('/api/v1/applications', { params: { header: { 'Idempotency-Key': key } }, body });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async get(id: string): Promise<WithEtag<Application>> {
    const r = await api.GET('/api/v1/applications/{id}', { params: { path: { id } } });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async amend(id: string, etag: string, body: AmendBody): Promise<WithEtag<Application>> {
    const r = await api.PATCH('/api/v1/applications/{id}', { params: { path: { id }, header: { 'If-Match': etag } }, body });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async addApplicant(id: string, etag: string, body: Body<'addApplicant'>): Promise<WithEtag<Application>> {
    const r = await api.POST('/api/v1/applications/{id}/applicants', { params: { path: { id }, header: { 'If-Match': etag, ...idem() } }, body });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async removeApplicant(id: string, etag: string, partyId: string): Promise<WithEtag<Application>> {
    const r = await api.DELETE('/api/v1/applications/{id}/applicants/{partyId}', { params: { path: { id, partyId }, header: { 'If-Match': etag } } });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async act(id: string, etag: string, action: ApplicationAction, body: ActionBody, key: string = idempotencyKey()): Promise<WithEtag<Application>> {
    const r = await api.POST('/api/v1/applications/{id}/actions/{action}', {
      params: { path: { id, action }, header: { 'If-Match': etag, 'Idempotency-Key': key } },
      body,
    });
    return { data: unwrap(r).data, etag: etagOf(r.response) };
  },
  async timeline(id: string): Promise<ApplicationEvent[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/timeline', { params: { path: { id } } })).data;
  },
  async asAt(id: string, t: string): Promise<AsAt> {
    return unwrap(await api.GET('/api/v1/applications/{id}/as-at', { params: { path: { id }, query: { t } } })).data;
  },
  async kyc(id: string): Promise<KycStatus> {
    return unwrap(await api.GET('/api/v1/applications/{id}/kyc', { params: { path: { id } } })).data;
  },
  async rescreen(id: string): Promise<KycStatus> {
    return unwrap(await api.POST('/api/v1/applications/{id}/kyc/actions/rescreen', { params: { path: { id }, header: idem() } })).data;
  },
};

export const alertsApi = {
  async list(params: { status?: readonly string[]; applicationId?: string; after?: string | null; size?: number } = {}): Promise<Page<ScreeningAlert>> {
    const query: operations['listScreeningAlerts']['parameters']['query'] = { 'page[size]': params.size ?? 25 };
    if (params.status && params.status.length > 0) query['filter[status]'] = params.status.join(',');
    if (params.applicationId) query['filter[application_id]'] = params.applicationId;
    if (params.after) query['page[after]'] = params.after;
    return unwrap(await api.GET('/api/v1/screening-alerts', { params: { query } }));
  },
  async get(id: string): Promise<ScreeningAlert> {
    return unwrap(await api.GET('/api/v1/screening-alerts/{id}', { params: { path: { id } } })).data;
  },
  async disposition(id: string, step: AlertStep, body: AlertDispositionBody, key: string = idempotencyKey()): Promise<ScreeningAlert> {
    return unwrap(await api.POST('/api/v1/screening-alerts/{id}/actions/{step}', { params: { path: { id, step }, header: { 'Idempotency-Key': key } }, body })).data;
  },
};

export type UploadInput = {
  file: File;
  documentType: string;
  checklistItemId?: string | null;
  partyId?: string | null;
  title?: string | null;
};

export const documentsApi = {
  async list(applicationId: string): Promise<DocumentRecord[]> {
    return unwrap(await api.GET('/api/v1/applications/{id}/documents', { params: { path: { id: applicationId } } })).data;
  },
  async get(id: string): Promise<DocumentRecord> {
    return unwrap(await api.GET('/api/v1/documents/{id}', { params: { path: { id } } })).data;
  },
  /** Multipart upload through the policy client (XSRF + Idempotency-Key + retries). */
  async upload(applicationId: string, input: UploadInput, key: string = idempotencyKey()): Promise<DocumentRecord> {
    const fields = {
      document_type: input.documentType,
      checklist_item_id: input.checklistItemId ?? null,
      party_id: input.partyId ?? null,
      title: input.title ?? null,
    };
    const r = await api.POST('/api/v1/applications/{id}/documents', {
      params: { path: { id: applicationId }, header: { 'Idempotency-Key': key } },
      // The contract types `file` as a binary string; the serializer sends the File itself.
      body: { ...fields, file: input.file.name },
      bodySerializer: formBody({ ...fields, file: input.file }),
    });
    return unwrap(r).data;
  },
  /** Binary download of a clean version (423 when quarantined, thrown as ApiProblem). */
  async content(documentId: string, versionId: string): Promise<Blob> {
    const r = await api.GET('/api/v1/documents/{id}/versions/{versionId}/content', {
      params: { path: { id: documentId, versionId } },
      parseAs: 'blob',
    });
    return unwrap(r);
  },
  async checklist(applicationId: string): Promise<Checklist> {
    return unwrap(await api.GET('/api/v1/applications/{id}/checklist', { params: { path: { id: applicationId } } })).data;
  },
  /** verify/reject → the item (200); waive → a pending change request (202). */
  async actOnItem(itemId: string, action: ChecklistAction, body: ChecklistActionBody): Promise<{ kind: 'item'; item: ChecklistItem } | { kind: 'change_request'; changeRequest: ChangeRequest }> {
    const r = await api.POST('/api/v1/checklist-items/{id}/actions/{action}', { params: { path: { id: itemId, action }, header: idem() }, body });
    const data = unwrap(r).data;
    if (r.response.status === 202) return { kind: 'change_request', changeRequest: data as ChangeRequest };
    return { kind: 'item', item: data as ChecklistItem };
  },
};
