// HRMS PHP app.js
// Storage can throw when cookies are blocked — never let that kill the UI.
function storeGet(k){ try{ return localStorage.getItem(k); }catch(err){ return null; } }
function storeSet(k,v){ try{ localStorage.setItem(k,v); }catch(err){} }
document.addEventListener('DOMContentLoaded', ()=>{
  // theme toggle
  const root = document.documentElement;
  const saved = storeGet('theme');
  if(saved) root.classList.toggle('dark', saved==='dark');
  const savedDataTheme = storeGet('data-theme');
  if(savedDataTheme) root.setAttribute('data-theme', savedDataTheme);
  // sidebar collapse
  const collapsed = storeGet('sidebar-collapsed')==='1';
  if(collapsed) document.body.classList.add('sidebar-collapsed');
  // mobile drawer
  const btnMobile = document.getElementById('btn-mobile-menu');
  const drawer = document.getElementById('mobile-drawer');
  const overlay = document.getElementById('drawer-overlay');
  function openDrawer(){ drawer?.classList.remove('hidden'); document.body.style.overflow='hidden'; }
  function closeDrawer(){ drawer?.classList.add('hidden'); document.body.style.overflow=''; }
  btnMobile?.addEventListener('click', openDrawer);
  overlay?.addEventListener('click', closeDrawer);
  drawer?.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click', closeDrawer));
  drawer?.querySelectorAll('a').forEach(a=>a.addEventListener('click', closeDrawer));
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); });
  // collapse toggle
  const btnCollapse = document.getElementById('btn-collapse');
  function syncCollapseIcon(){
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    if(btnCollapse) btnCollapse.title = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
  }
  btnCollapse?.addEventListener('click', ()=>{
    const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    storeSet('sidebar-collapsed', isCollapsed?'1':'0');
    syncCollapseIcon();
  });
  syncCollapseIcon();
  // parent rows expand their submenu when clicked (no arrow indicators);
  // first click opens in place, second click follows the link
  try{
    const openIds = JSON.parse(sessionStorage.getItem('sb-open') || '[]');    openIds.forEach(id=>{
      const list = document.getElementById(id);
      if(list && list.classList.contains('sb-sub')) list.classList.remove('is-collapsed');
    });
  }catch(err){}
  function persistSbOpen(){
    const ids = Array.from(document.querySelectorAll('.sb-sub:not(.is-collapsed)')).map(el=>el.id).filter(Boolean);
    try{ sessionStorage.setItem('sb-open', JSON.stringify(ids)); }catch(err){}
  }
  document.querySelectorAll('[data-sb-parent]').forEach(link=>{
    link.addEventListener('click', (e)=>{
      const list = document.getElementById(link.dataset.sbParent);
      if(!list) return;
      if(list.classList.contains('is-collapsed')){
        e.preventDefault();
        list.classList.remove('is-collapsed');
        persistSbOpen();
      }
    });
  });
  // notification dropdown
  const btnNotif = document.getElementById('btn-notif');
  const notifDrop = document.getElementById('notif-dropdown');
  function toggleNotif(open){
    if(!notifDrop) return;
    const show = open ?? notifDrop.classList.contains('hidden');
    notifDrop.classList.toggle('hidden', !show);
    btnNotif?.setAttribute('aria-expanded', String(show));
  }
  btnNotif?.addEventListener('click', (e)=>{ e.stopPropagation(); toggleNotif(); });
  document.addEventListener('click', (e)=>{
    if(notifDrop && !notifDrop.classList.contains('hidden') && !notifDrop.contains(e.target)) toggleNotif(false);
  });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') toggleNotif(false); });
  document.getElementById('btn-theme')?.addEventListener('click', ()=>{
    const isDark = root.classList.toggle('dark');
    storeSet('theme', isDark?'dark':'light');
  });
  // search palette Ctrl+K
  const palette = document.getElementById('cmd-palette');
  function togglePalette(open){
    if(!palette) return;
    palette.classList.toggle('hidden', !open);
    if(open) palette.querySelector('input')?.focus();
  }
  document.getElementById('btn-search')?.addEventListener('click', ()=>togglePalette(true));
  document.addEventListener('keydown', (e)=>{
    if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); togglePalette(!palette.classList.contains('hidden')); }
    if(e.key==='Escape') togglePalette(false);
  });
  palette?.querySelector('[data-close]')?.addEventListener('click', ()=>togglePalette(false));
  palette?.addEventListener('click', (e)=>{ if(e.target===palette) togglePalette(false); });
  // toast auto hide
  document.querySelectorAll('.toast').forEach(t=>setTimeout(()=>t.remove(), 4000));
  // table search filter (client)
  document.querySelectorAll('[data-table-search]').forEach(input=>{
    const targetId = input.getAttribute('data-table-search');
    const table = document.getElementById(targetId);
    if(!table) return;
    input.addEventListener('input', ()=>{
      const q = input.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(tr=>{
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });
});
function confirmDelete(msg){ return confirm(msg||'Are you sure you want to delete this?'); }
