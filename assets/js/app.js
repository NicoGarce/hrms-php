// HRMS PHP app.js
document.addEventListener('DOMContentLoaded', ()=>{
  // theme toggle
  const root = document.documentElement;
  const saved = localStorage.getItem('theme');
  if(saved) root.classList.toggle('dark', saved==='dark');
  const savedDataTheme = localStorage.getItem('data-theme');
  if(savedDataTheme) root.setAttribute('data-theme', savedDataTheme);
  // sidebar collapse
  const collapsed = localStorage.getItem('sidebar-collapsed')==='1';
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
    localStorage.setItem('sidebar-collapsed', isCollapsed?'1':'0');
    syncCollapseIcon();
  });
  syncCollapseIcon();
  // collapsible sub-navigation
  document.querySelectorAll('[data-sb-toggle]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const list = document.getElementById(btn.dataset.sbToggle);
      if(!list) return;
      const collapsed = list.classList.toggle('is-collapsed');
      btn.classList.toggle('is-collapsed', collapsed);
      btn.setAttribute('aria-expanded', String(!collapsed));
    });
  });
  // theme button
  document.getElementById('btn-theme')?.addEventListener('click', ()=>{
    const isDark = root.classList.toggle('dark');
    localStorage.setItem('theme', isDark?'dark':'light');
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
