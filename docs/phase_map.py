"""Single source of truth: requirement -> delivery phase + module.
Phases: P0 Foundation, P1 MVP origination, P2 Documents & IDP, P3 Decisioning & workflow depth,
P4 Integrations live & channels, P5 Compliance, reporting & data, P6 Hardening & certification, V2 deferred.
Used by build_matrix.py to produce 08-traceability-matrix.
"""
MODULE = {
 "TEN": "M01 Platform", "CFG": "M01 Platform", "SEC": "M02 Identity & Access", "PRD": "M03 Product Factory",
 "CHN": "M04 Origination", "CUS": "M05 Party & KYC", "APP": "M06 Application", "DOC": "M07 Documents & IDP",
 "CRD": "M08 Decisioning", "COL": "M09 Collateral", "WFL": "M10 Workflow", "APV": "M11 Approvals",
 "OFR": "M12 Offer & Execution", "CPR": "M13 Conditions", "DSB": "M14 Disbursement", "HND": "M15 Handover",
 "NTF": "M16 Notifications", "CBA": "M17 Integration Runtime", "CMP": "M18 Compliance", "AUD": "M19 Audit & Evidence",
 "RPT": "M20 Reporting",
}
TRD = {
 "TEN": "§2.3, §4 M01, §5", "CFG": "§2.7, §4 M01", "SEC": "§8", "PRD": "§4 M03, §7", "CHN": "§4 M04, §10",
 "CUS": "§4 M05, §9.2", "APP": "§4 M06, §5.2, §6", "DOC": "§4 M07/S1, §5.2", "CRD": "§7", "COL": "§4 M09",
 "WFL": "§6.1–6.5", "APV": "§6.3", "OFR": "§4 M12", "CPR": "§4 M13, §6.2", "DSB": "§11, 04 §2.3",
 "HND": "§4 M15", "NTF": "§4 M16", "CBA": "§11, 04", "CMP": "§9", "AUD": "§5.4", "RPT": "§4 M20, §8.1",
}
# Explicit per-requirement phases. Every BRD FR is listed explicitly (verified by tools/verify_plan.py).
P = {}
def setp(phase, ids):
    for i in ids.split():
        P[i] = phase

# ---- MVP (P1) and foundation (P0) ----
setp("P0", "FR-TEN-001 FR-TEN-003 FR-TEN-009 FR-SEC-001 FR-SEC-002 FR-SEC-003 FR-SEC-004 FR-SEC-005 FR-SEC-006 "
           "FR-SEC-007 FR-SEC-008 FR-SEC-011 FR-SEC-013 FR-SEC-014 FR-SEC-015 FR-SEC-016 FR-SEC-017 FR-SEC-019 "
           "FR-AUD-001 FR-AUD-002 FR-AUD-003 FR-AUD-004 FR-AUD-005 FR-AUD-007 FR-AUD-012 "
           "FR-CBA-001 FR-CBA-002 FR-CBA-007 FR-CBA-008 FR-CBA-010 FR-CBA-011 FR-CBA-016 FR-CBA-020 FR-CMP-036")
setp("P1", "FR-TEN-006 FR-TEN-007 FR-PRD-001 FR-PRD-002 FR-PRD-003 FR-PRD-004 FR-PRD-005 FR-PRD-006 FR-PRD-009 "
           "FR-CHN-001 FR-CHN-002 FR-CHN-003 FR-CHN-007 "
           "FR-CUS-001 FR-CUS-002 FR-CUS-003 FR-CUS-005 FR-CUS-006 FR-CUS-007 FR-CUS-008 FR-CUS-009 FR-CUS-011 "
           "FR-APP-001 FR-APP-002 FR-APP-004 FR-APP-005 FR-APP-006 FR-APP-008 FR-APP-009 "
           "FR-DOC-001 FR-DOC-002 FR-DOC-004 FR-DOC-005 FR-DOC-006 FR-DOC-007 FR-DOC-008 FR-DOC-009 "
           "FR-CRD-001 FR-CRD-002 FR-CRD-003 FR-CRD-004 FR-CRD-008 FR-CRD-010 FR-CRD-011 FR-CRD-013 FR-CRD-014 FR-CRD-015 "
           "FR-COL-001 FR-COL-002 FR-COL-003 FR-COL-004 FR-COL-005 FR-COL-006 FR-COL-008 "
           "FR-WFL-001 FR-WFL-003 FR-WFL-004 FR-WFL-005 FR-WFL-006 FR-WFL-007 FR-WFL-008 FR-WFL-009 FR-WFL-010 FR-WFL-011 FR-WFL-012 "
           "FR-APV-001 FR-APV-002 FR-APV-003 FR-APV-005 FR-APV-006 FR-APV-007 FR-APV-008 FR-APV-009 FR-APV-010 FR-APV-012 "
           "FR-OFR-001 FR-OFR-002 FR-OFR-003 FR-OFR-004 FR-OFR-005 FR-OFR-006 FR-OFR-007 FR-OFR-008 FR-OFR-009 FR-OFR-010 "
           "FR-CPR-001 FR-CPR-002 FR-CPR-003 FR-CPR-004 FR-CPR-005 "
           "FR-DSB-001 FR-DSB-002 FR-DSB-004 FR-DSB-005 FR-DSB-006 FR-DSB-007 FR-DSB-008 FR-DSB-009 FR-DSB-011 "
           "FR-HND-001 FR-HND-003 FR-HND-004 FR-NTF-001 FR-NTF-002 FR-NTF-005 "
           "FR-CBA-003 FR-CBA-004 FR-CBA-005 FR-CBA-009 FR-CBA-012 FR-CBA-013 FR-CBA-015 FR-CBA-017 FR-CBA-019 "
           "FR-CMP-001 FR-CMP-003 FR-CMP-010 FR-CMP-011 FR-CMP-013 FR-CMP-014 FR-CMP-017 FR-CMP-020 FR-CMP-021 "
           "FR-CMP-015 FR-CMP-023 FR-CMP-025 FR-CMP-027 FR-CMP-032 FR-CMP-043 FR-AUD-013 "
           "FR-AUD-006 FR-AUD-008 FR-AUD-009 FR-AUD-010 FR-AUD-011 FR-AUD-016 FR-RPT-001 FR-RPT-010 FR-CFG-001 FR-CFG-002")
# ---- later phases ----
setp("P2", "FR-DOC-003 FR-DOC-010 FR-DOC-020 FR-DOC-021 FR-DOC-022 FR-DOC-023 FR-DOC-024 FR-DOC-025 FR-DOC-026 FR-DOC-027 "
           "FR-DOC-028 FR-DOC-029 FR-DOC-040 FR-DOC-041 FR-DOC-042 FR-DOC-043 FR-DOC-044 FR-DOC-045 FR-DOC-046 "
           "FR-DOC-050 FR-DOC-052 FR-DOC-053 FR-DOC-054")
setp("P3", "FR-PRD-007 FR-PRD-008 FR-CUS-010 FR-APP-003 FR-APP-007 FR-CRD-005 FR-CRD-006 FR-CRD-007 FR-CRD-009 FR-CRD-012 "
           "FR-CRD-017 FR-WFL-002 FR-APV-004 FR-APV-011 FR-CPR-006 FR-DSB-003 FR-DSB-012 FR-HND-005 FR-CMP-024 "
           "FR-CMP-040 FR-CMP-041 FR-CMP-042 FR-TEN-005")
setp("P4", "FR-CHN-004 FR-CHN-005 FR-CHN-006 FR-CHN-008 FR-CUS-004 FR-DOC-051 FR-COL-007 FR-DSB-010 FR-HND-002 "
           "FR-NTF-003 FR-NTF-004 FR-NTF-006 FR-CBA-006 FR-CMP-012 FR-SEC-012 FR-SEC-020")
setp("P5", "FR-TEN-004 FR-TEN-008 FR-TEN-010 FR-TEN-011 FR-CMP-016 FR-CMP-022 FR-CMP-026 FR-CMP-030 FR-CMP-031 "
           "FR-CMP-033 FR-CMP-034 FR-CMP-035 FR-CMP-037 FR-CMP-044 FR-CMP-045 FR-AUD-014 FR-AUD-015 "
           "FR-RPT-002 FR-RPT-003 FR-RPT-004 FR-RPT-005 FR-RPT-006 FR-RPT-007 FR-RPT-008 FR-RPT-009 "
           "FR-SEC-009 FR-SEC-010 FR-SEC-018 FR-CFG-003")
setp("P6", "FR-TEN-002 FR-CBA-014 FR-CBA-018 FR-CFG-004")
setp("V2", "FR-PRD-010 FR-DOC-030 FR-CRD-016 FR-CMP-002")

UNNUMBERED_PHASE = {
 "LOS-FR-274": ("P5", "M21 Licensing & Operator"), "LOS-FR-275": ("P5", "M17 Integration Runtime"),
 "LOS-FR-276": ("P5", "M21 Licensing & Operator"), "LOS-FR-277": ("P5", "M21 Licensing & Operator"),
 "LOS-FR-278": ("P0", "M02 Identity & Access"), "LOS-FR-279": ("P1", "M02 Identity & Access"),
 "LOS-FR-280": ("P5", "M02 Identity & Access"), "LOS-FR-281": ("P4", "M02 Identity & Access"),
 "LOS-FR-282": ("P1", "M06 Application"), "LOS-FR-283": ("P1", "M06 Application"),
 "LOS-FR-284": ("P0", "M01 Platform"), "LOS-FR-285": ("P4", "M17 Integration Runtime"),
 "LOS-FR-286": ("P4", "M17 Integration Runtime"), "LOS-FR-287": ("P4", "M17 Integration Runtime"),
 "LOS-FR-301": ("P1", "M06 Application"), "LOS-FR-302": ("P0", "M02 Identity & Access"),
 "LOS-FR-303": ("P4", "M04 Origination"), "LOS-FR-304": ("P4", "M16 Notifications"),
 "LOS-FR-305": ("P1", "M02 Identity & Access"), "LOS-FR-306": ("P3", "M18 Compliance"),
 "LOS-FR-307": ("P1", "M01 Platform"), "LOS-FR-308": ("P3", "M08 Decisioning"),
 "LOS-FR-309": ("P4", "M17 Integration Runtime"), "LOS-FR-310": ("P1", "M18 Compliance"),
 "LOS-FR-311": ("P4", "M05 Party & KYC"), "LOS-FR-312": ("P4", "M04 Origination"),
 "LOS-FR-313": ("P1", "M14 Disbursement"), "LOS-FR-314": ("P1", "M12 Offer & Execution"),
 "LOS-FR-315": ("P4", "M17 Integration Runtime"), "LOS-FR-316": ("P0", "M21 Licensing & Operator"),
}
NFR_PHASE = {  # baseline instrumented in P0/P1; certified in P6
 "NFR-011": "P0", "NFR-012": "P1", "NFR-013": "P1", "NFR-014": "P1", "NFR-017": "P1", "NFR-019": "P0",
 "NFR-006": "P1", "NFR-008": "P6", "NFR-009": "P0", "NFR-010": "P6", "NFR-018": "P1",
 # added 2026-10-08 (plan issue 1): baselines measured from P1, certified in P6
 "NFR-001": "P6", "NFR-002": "P6", "NFR-003": "P2", "NFR-004": "P6", "NFR-005": "P6", "NFR-007": "P6",
 "NFR-015": "P1", "NFR-016": "P1", "NFR-020": "P5",
}
CON_PHASE = "P0"  # LOS-CON-001..013 are binding from the first commit and verified continuously
PHASE_NAMES = {
 "P0": "Foundation", "P1": "MVP origination", "P2": "Documents & IDP", "P3": "Decisioning & workflow depth",
 "P4": "Live integrations & channels", "P5": "Compliance, reporting & data", "P6": "Hardening & certification", "V2": "Deferred (v2)",
}

# Requirements delivered partially in their phase and completed later: id -> (what lands first, completing phase)
COMPLETES = {
 "FR-CHN-001": ("staff-assisted channel + API", "P4"), "FR-DOC-001": ("web upload + API", "P4"),
 "FR-CUS-001": ("individual + limited company", "P3"), "FR-CUS-003": ("port + stub adapter", "P4"),
 "FR-CUS-005": ("port + stub adapter, intake/approval/pre-disbursement", "P4"), "FR-CUS-009": ("via CBA simulator", "P4"),
 "FR-CRD-003": ("one bureau via stub", "P4"), "FR-NTF-001": ("email + in-app + webhook", "P4"),
 "FR-OFR-004": ("email + printable copy", "P4"), "FR-OFR-006": ("port + stub e-sign", "P4"),
 "FR-CPR-004": ("re-screen via stub", "P4"), "FR-CPR-005": ("name enquiry via simulator", "P4"),
 "FR-DSB-002": ("full single disbursement", "P3"), "FR-APV-003": ("sequential chains", "P3"),
 "FR-CMP-001": ("pack framework + Nigeria commercial-bank v0", "P5"), "FR-RPT-001": ("pipeline/SLA dashboard", "P5"),
 "FR-CFG-001": ("products, rules, matrices, users, workflow JSON", "P5"), "FR-CBA-013": ("kit against simulator", "P6"),
 "FR-WFL-005": ("reassignment + delegation", "P3"), "FR-CMP-013": ("alert workspace with four-eyes", "P4"),
 "FR-CMP-023": ("single-obligor limit", "P3"), "FR-SEC-016": ("TLS + app-level envelope encryption, local KEK", "P6"),
 "FR-CRD-002": ("tables + expressions; simulation against stored snapshots", "P3"), "FR-AUD-010": ("evidence pack ZIP, signed manifest", "P5"),
 "FR-AUD-013": ("Object Lock for documents + audit checkpoints (single-node MinIO)", "P6"),
 "LOS-FR-316": ("offline signed licence + enforcement", "P4"),
}
