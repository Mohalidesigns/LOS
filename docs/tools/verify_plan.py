"""Verifies 06-development-plan task catalogue (plan_tasks.py) against phase_map.py and the inventory. Run: python3 docs/tools/verify_plan.py"""
import csv, sys, importlib.util, collections
import os
HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from plan_tasks import T, V2, PROPOSED
D = os.path.join(HERE, '..') + os.sep
spec = importlib.util.spec_from_file_location('pm', D + 'phase_map.py'); pm = importlib.util.module_from_spec(spec); spec.loader.exec_module(pm)
rows = list(csv.DictReader(open(D + '01-requirements-inventory.csv')))

def key(x):
    b = x['brd_id']
    if b.startswith('FR-') or b.startswith('NFR-'): return b
    return x['los_id']
INV = {key(x): x for x in rows}
assert len(INV) == len(rows) == 336

def mapped_phase(k):
    if k in pm.P: return pm.P[k], 'phase_map.P'
    if k in pm.UNNUMBERED_PHASE: return pm.UNNUMBERED_PHASE[k][0], 'phase_map.UNNUMBERED_PHASE'
    if k in pm.NFR_PHASE: return pm.NFR_PHASE[k], 'phase_map.NFR_PHASE'
    if k in PROPOSED: return PROPOSED[k], 'PROPOSED (not in phase_map.py)'
    return None, 'UNASSIGNED'

errors = []
where = collections.defaultdict(list)
for t in T:
    for r in t['delivers']: where[r].append(t['id'])
for r in V2: where[r].append('V2')
for k in INV:
    if len(where[k]) != 1: errors.append(f'{k} appears {len(where[k])}x: {where[k]}')
for k in where:
    if k not in INV: errors.append(f'{k} not in inventory')
# phase consistency
for t in T:
    ph = t['id'][:2]
    for r in t['delivers']:
        mp, src = mapped_phase(r)
        if mp != ph: errors.append(f'{r} in {t["id"]} but mapped {mp} ({src})')
    for r in t['completes']:
        if r not in pm.COMPLETES: errors.append(f'{r} completes but not in COMPLETES')
        elif pm.COMPLETES[r][1] != ph: errors.append(f'{r} completion in {ph}, COMPLETES says {pm.COMPLETES[r][1]}')
for r in V2:
    if pm.P.get(r) != 'V2': errors.append(f'{r} V2 mismatch')
comp = collections.Counter(c for t in T for c in t['completes'])
for r in pm.COMPLETES:
    if comp[r] != 1: errors.append(f'COMPLETES {r} closed {comp[r]}x')
ids = {t['id'] for t in T}
assert len(ids) == len(T)
order = {f'P{i}': i for i in range(7)}
for t in T:
    for d in t['deps']:
        if d not in ids: errors.append(f'{t["id"]} dep {d} missing')
        elif order[d[:2]] > order[t['id'][:2]]: errors.append(f'{t["id"]} depends on later {d}')

# summary
cat = collections.Counter()
for k, x in INV.items():
    if x['brd_id'].startswith('FR-'): c = 'BRD FR'
    elif k.startswith('NFR-'): c = 'NFR'
    elif k.startswith('LOS-CON'): c = 'LOS-CON'
    else: c = 'LOS-FR (no BRD ID)'
    cat[c] += 1
src = collections.Counter(mapped_phase(k)[1] for k in INV)
print('inventory', dict(cat))
print('phase source', dict(src))
print('tasks', len(T), 'errors', len(errors))
for e in errors: print('  ', e)
sys.exit(1 if errors else 0)
