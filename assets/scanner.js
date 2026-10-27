(() => {
 const input=document.getElementById('ticket-token'), video=document.getElementById('scanner-video'), canvas=document.getElementById('scanner-canvas');
 const start=document.querySelector('[data-camera-start]'),stopButton=document.querySelector('[data-camera-stop]'),status=document.querySelector('[data-scanner-status]'),placeholder=document.querySelector('[data-scanner-placeholder]');let stream=null,frame=0;
 function tokenFrom(value){const m=value.match(/(?:^|#token=)(v1\.[0-9]+\.[0-9]+\.[0-9]+\.[a-f0-9]{64})$/);return m?m[1]:null;}
 const token=tokenFrom(location.hash);if(token){input.value=token;history.replaceState(null,'',location.pathname);}
 const saved=sessionStorage.getItem('fairboard-scan');if(saved){input.value=saved;sessionStorage.removeItem('fairboard-scan');}
 function stop(){cancelAnimationFrame(frame);if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;video.hidden=true;placeholder.hidden=false;stopButton.hidden=true;start.hidden=false;}
 function detect(){
  if(!stream)return;
  if(video.readyState>=2){canvas.width=Math.min(video.videoWidth,960);canvas.height=Math.round(video.videoHeight*canvas.width/video.videoWidth);const ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.drawImage(video,0,0,canvas.width,canvas.height);const data=ctx.getImageData(0,0,canvas.width,canvas.height);const qr=window.jsQR(data.data,data.width,data.height);if(qr){const token=tokenFrom(qr.data);if(token){input.value=token;status.textContent='Code captured. Confirm check-in now.';stop();input.focus();return;}}}
  frame=requestAnimationFrame(detect);
 }
 start.addEventListener('click',async()=>{try{if(!navigator.mediaDevices||!window.jsQR)throw new Error();stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});video.srcObject=stream;await video.play();video.hidden=false;placeholder.hidden=true;start.hidden=true;stopButton.hidden=false;status.textContent='Point the camera at a live Fairboard QR code.';detect();}catch{stop();status.textContent='Camera unavailable. Allow camera permission on HTTPS, or paste a ticket link.';}});
 stopButton.addEventListener('click',stop);window.addEventListener('pagehide',stop);document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});
})();
