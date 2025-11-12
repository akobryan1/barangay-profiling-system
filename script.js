
const api = (action, payload={}, method='POST') => {
  const opts = { method };
  if (method === 'GET') {
    const params = new URLSearchParams({action, ...payload}).toString();
    return fetch('api.php?' + params).then(r=>r.json());
  } else {
    const fd = new FormData();
    fd.append('action', action);
    Object.entries(payload).forEach(([k,v])=>fd.append(k, v));
    opts.body = fd;
    return fetch('api.php', opts).then(r=>r.json());
  }
};

// Tabs
document.querySelectorAll('.tab').forEach(btn=>{
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    btn.classList.add('active');
    const view = btn.getAttribute('data-view');
    document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
    document.getElementById('view-'+view).classList.add('active');
    if (view==='families') loadFamilies(); else loadResidents();
  });
});

// Modals
const residentModal = document.getElementById('residentModal');
const familyModal = document.getElementById('familyModal');
const residentForm = document.getElementById('residentForm');
const familyForm = document.getElementById('familyForm');

function openResidentModal(editData){
  document.getElementById('residentModalTitle').innerText = editData ? 'Edit Resident' : 'Add Resident';
  residentForm.reset();
  document.getElementById('residentAction').value = editData ? 'updateResident' : 'createResident';
  document.getElementById('belongs').value = editData ? 'yes' : 'yes';
  toggleBelongs();
  loadFamiliesSelect();
  if (editData){
    const map = {
      person_id: editData.person_id,
      first_name: editData.first_name,
      middle_name: editData.middle_name,
      last_name: editData.last_name,
      gender: editData.gender,
      date_of_birth: editData.date_of_birth,
      relationship_to_head: editData.relationship_to_head,
      occupation: editData.occupation,
      educational_attainment: editData.educational_attainment,
      contact_number: editData.contact_number,
      civil_status: editData.civil_status,
      religion: editData.religion
    };
    Object.entries(map).forEach(([k,v]) => { const el=document.getElementById(k); if (el) el.value = v ?? ''; });
    document.getElementById('existing_family_id').value = editData.family_id;
  }
  residentModal.classList.add('open');
}
function closeResidentModal(){ residentModal.classList.remove('open'); }

function openFamilyModal(editData){
  document.getElementById('familyModalTitle').innerText = editData ? 'Edit Family' : 'Add Family';
  familyForm.reset();
  if (editData){
    document.getElementById('family_id').value = editData.family_id;
    document.getElementById('family_name').value = editData.family_name;
    document.getElementById('household_number').value = editData.household_number ?? '';
    document.getElementById('address').value = editData.address ?? '';
  }
  familyModal.classList.add('open');
}
function closeFamilyModal(){ familyModal.classList.remove('open'); }

function toggleBelongs(){
  const v = document.getElementById('belongs').value;
  document.getElementById('existingFamilyArea').style.display = (v==='yes')?'block':'none';
  document.getElementById('newFamilyArea').style.display = (v==='no')?'block':'none';
}
document.getElementById('belongs').addEventListener('change', toggleBelongs);

// Load families into select (for resident form)
async function loadFamiliesSelect(){
  const res = await api('listFamilies', {}, 'GET');
  const sel = document.getElementById('existing_family_id');
  sel.innerHTML = '';
  (res.data || []).forEach(f=>{
    const opt = document.createElement('option');
    opt.value = f.family_id; opt.textContent = f.family_name;
    sel.appendChild(opt);
  });
}

// Residents table
function renderResidents(rows){
  const tbody = document.querySelector('#residentTable tbody');
  tbody.innerHTML='';
  rows.forEach(r=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.person_id}</td>
      <td>${escapeHtml(r.first_name)} ${r.middle_name?escapeHtml(r.middle_name)+' ':''}${escapeHtml(r.last_name)}</td>
      <td><span class="badge">${escapeHtml(r.family_name)}</span></td>
      <td>${escapeHtml(r.gender)}</td>
      <td>${escapeHtml(r.date_of_birth)}</td>
      <td>${r.age ?? ''}</td>
      <td>${escapeHtml(r.relationship_to_head)}</td>
      <td>
        <button class="ghost" data-edit='${JSON.stringify(r)}'>Edit</button>
        <button data-del="${r.person_id}">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}
function escapeHtml(s){ return s==null?'' : s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

async function loadResidents(){
  const res = await api('listResidents', {}, 'GET');
  if(res.ok) renderResidents(res.data); else alert(res.error||'Error');
}

document.getElementById('addResidentBtn').addEventListener('click', ()=>openResidentModal());
document.getElementById('addFamilyBtn').addEventListener('click', ()=>openFamilyModal());

// Save resident
document.getElementById('saveResidentBtn').addEventListener('click', async ()=>{
  const fd = new FormData(residentForm);
  const action = fd.get('action');
  if (action==='updateResident'){
    fd.append('family_id', document.getElementById('existing_family_id').value);
  }
  const res = await fetch('api.php', { method:'POST', body: fd }).then(r=>r.json());
  if (res.ok){ closeResidentModal(); loadResidents(); } else alert(res.error||'Failed');
});

// Delegated actions for residents
document.querySelector('#residentTable tbody').addEventListener('click', async (e)=>{
  const editBtn = e.target.closest('button[data-edit]');
  const delBtn = e.target.closest('button[data-del]');
  if (editBtn){
    const data = JSON.parse(editBtn.getAttribute('data-edit'));
    openResidentModal(data);
  } else if (delBtn){
    if (confirm('Delete this resident?')){
      const res = await api('deleteResident', {person_id: delBtn.getAttribute('data-del')});
      if(res.ok){ loadResidents(); } else alert(res.error||'Failed');
    }
  }
});

// Families table
function renderFamilies(rows){
  const tbody = document.querySelector('#familyTable tbody');
  tbody.innerHTML='';
  rows.forEach(f=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${f.family_id}</td>
      <td>${escapeHtml(f.family_name)}</td>
      <td>${escapeHtml(f.head_name || '')}</td>
      <td>${escapeHtml(f.address || '')}</td>
      <td>${f.members || 0}</td>
      <td>
        <button class="ghost" data-fedit='${JSON.stringify(f)}'>Edit</button>
        <button data-fdel="${f.family_id}">Delete</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}
async function loadFamilies(){
  const res = await api('listFamiliesWithMembers', {}, 'GET');
  if (res.ok) renderFamilies(res.data); else alert(res.error||'Error loading families');
}

// Save family
document.getElementById('saveFamilyBtn').addEventListener('click', async ()=>{
  const payload = {
    family_id: document.getElementById('family_id').value,
    family_name: document.getElementById('family_name').value,
    household_number: document.getElementById('household_number').value,
    address: document.getElementById('address').value,
    barangay_id: 1
  };
  let res;
  if (payload.family_id){
    res = await api('updateFamily', payload);
  } else {
    res = await api('createFamily', payload);
  }
  if(res.ok){ closeFamilyModal(); loadFamiliesSelect(); loadFamilies(); } else alert(res.error||'Failed');
});

// Delegated actions for families
document.querySelector('#familyTable tbody').addEventListener('click', async (e)=>{
  const editBtn = e.target.closest('button[data-fedit]');
  const delBtn = e.target.closest('button[data-fdel]');
  if (editBtn){
    const data = JSON.parse(editBtn.getAttribute('data-fedit'));
    openFamilyModal(data);
  } else if (delBtn){
    if (confirm('Delete this family? This will fail if residents still reference it.')){
      const res = await api('deleteFamily', {family_id: delBtn.getAttribute('data-fdel')});
      if(res.ok){ loadFamilies(); loadFamiliesSelect(); } else alert(res.error||'Failed');
    }
  }
});

// Search across residents or families
document.getElementById('searchBox').addEventListener('input', async (e)=>{
  const term = e.target.value.trim();
  const activeView = document.querySelector('.tab.active').getAttribute('data-view');
  if (activeView==='residents'){
    if (term===''){ loadResidents(); return; }
    const res = await api('searchResidents', {term}, 'GET');
    if (res.ok) renderResidents(res.data);
  } else {
    const rows = document.querySelectorAll('#familyTable tbody tr');
    rows.forEach(row=>{
      const txt = row.textContent.toLowerCase();
      row.style.display = txt.includes(term.toLowerCase()) ? '' : 'none';
    });
  }
});

function init(){
  loadFamiliesSelect();
  loadResidents();
}
init();
