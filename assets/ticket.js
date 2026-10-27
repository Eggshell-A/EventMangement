(() => {
 const root=document.querySelector('[data-ticket]'); if(!root)return;
 const image=root.querySelector('[data-ticket-image]'), status=root.querySelector('[data-ticket-status]'), progress=root.querySelector('progress');
 let expiry=0, offset=0, loading=false, nextTry=0, terminal=false;
 async function refresh(){
  if(loading||terminal||document.hidden)return; loading=true;
  const controller=new AbortController(), timeout=setTimeout(()=>controller.abort(),10000);
  try {
   const res=await fetch('ticket_qr.php',{method:'POST',credentials:'same-origin',cache:'no-store',signal:controller.signal,body:new URLSearchParams({csrf_token:document.querySelector('meta[name="csrf-token"]').content,booking_id:root.dataset.bookingId})});
   if(!res.ok){if(res.status===409||res.status===404)terminal=true;throw new Error();}
   const data=await res.json(); offset=data.server_time*1000-Date.now();expiry=data.expires_at*1000;image.src=data.image;image.hidden=false;nextTry=0;tick();
  }catch{image.hidden=true;expiry=0;nextTry=Date.now()+5000;status.textContent=terminal?'This ticket is no longer active.':'Unable to load a current code. Check your connection or sign in again.';progress.value=0;}
  finally{clearTimeout(timeout);loading=false;}
 }
 function tick(){
  if(expiry){const seconds=Math.max(0,Math.ceil((expiry-Date.now()-offset)/1000));progress.value=seconds;status.textContent=`Refreshes in ${seconds}s`;if(seconds===0){image.hidden=true;status.textContent='Refreshing ticket…';refresh();}}
  else if(Date.now()>nextTry&&!loading&&!terminal)refresh();
 }
 root.querySelector('[data-ticket-retry]').addEventListener('click',()=>{terminal=false;nextTry=0;refresh();});
 document.addEventListener('visibilitychange',()=>{image.hidden=true;expiry=0;if(!document.hidden)refresh();});
 setInterval(tick,200);refresh();
})();
