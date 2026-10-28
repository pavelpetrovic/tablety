(() => {
const box=document.querySelector('#calendar'),out=document.querySelector('#days');
if(!box||!out)return;
const cap=Number(box.dataset.capacity),past=box.dataset.past==='1';
const records=[...box.querySelectorAll('.reservation')].map(el=>({
 el,start:new Date(el.dataset.start.replace(' ','T')),end:new Date(el.dataset.end.replace(' ','T')),count:Number(el.dataset.count)
}));
const fmt=new Intl.DateTimeFormat('sk-SK',{weekday:'short',day:'numeric',month:'long',year:'numeric'});
const clock=new Intl.DateTimeFormat('sk-SK',{hour:'2-digit',minute:'2-digit'});
const today=new Date();today.setHours(0,0,0,0);
let offset=0;
function render(){
 out.replaceChildren();
 if(past){records.forEach(r=>{r.el.style.display='flex';out.append(r.el)});return;}
 const nav=document.createElement('div');nav.className='pager';
 const prev=document.createElement('button');prev.textContent='← Predchádzajúce';prev.disabled=offset===0;
 prev.onclick=()=>{offset=Math.max(0,offset-30);render()};
 const next=document.createElement('button');next.textContent='Ďalšie →';next.onclick=()=>{offset+=30;render()};
 const title=document.createElement('span');title.textContent='Dni '+(offset+1)+'–'+(offset+30);
 nav.append(prev,title,next);out.append(nav);
 for(let i=offset;i<offset+30;i++){
  const day=new Date(today);day.setDate(day.getDate()+i);
  const tomorrow=new Date(day);tomorrow.setDate(day.getDate()+1);
  const active=records.filter(r=>r.start<tomorrow&&r.end>day);
  const div=document.createElement('div');div.className='day';
  // Capacity status is computed from a sweep of interval endpoints within the day.
  const ev=[];for(const r of active){ev.push([Math.max(day.getTime(),r.start.getTime()),r.count]);ev.push([Math.min(tomorrow.getTime(),r.end.getTime()),-r.count]);}
  ev.sort((a,b)=>a[0]-b[0]||a[1]-b[1]);
  let used=0,max=0;for(const [,n] of ev){used+=n;max=Math.max(max,used);}
  if(max>=cap)div.classList.add('full');else if(max>0)div.classList.add('partial');
  const head=document.createElement('div');head.className='day-title';head.textContent=fmt.format(day)+(active.length?'':' · Voľné');div.append(head);
  for(const r of active){
   const row=document.createElement('div');row.className='entry';
   const label=document.createElement('span');
   const from=r.start>day?r.start:day,to=r.end<tomorrow?r.end:tomorrow;
   label.textContent=clock.format(from)+'–'+(to.getTime()===tomorrow.getTime()?'24:00':clock.format(to))+' · '+r.count+' ks · '+r.el.querySelector('strong').textContent;
   const actions=r.el.querySelector('.actions').cloneNode(true);
   // Cloned forms preserve CSRF tokens and submit normally.
   row.append(label,actions);row.id=r.el.id+'-day-'+i;div.append(row);
  }
  out.append(div);
 }
 if(location.hash.startsWith('#reservation-')){
  const id=location.hash.slice(1);
  const target=[...out.querySelectorAll('[id]')].find(e=>e.id.startsWith(id+'-day-'));
  if(target)target.scrollIntoView({block:'center'});
 }
}
render();
})();
