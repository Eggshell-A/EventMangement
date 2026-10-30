(() => {
 const menu=document.querySelector('[data-menu-toggle]');if(menu)menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')==='true';menu.setAttribute('aria-expanded',String(!open));document.getElementById('main-nav').classList.toggle('is-open',!open);});
 document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{const input=document.getElementById(button.dataset.passwordToggle),visible=input.type==='password';input.type=visible?'text':'password';button.textContent=visible?'Hide':'Show';button.setAttribute('aria-label',visible?'Hide password':'Show password');button.setAttribute('aria-pressed',String(visible));}));
 const form=document.querySelector('[data-registration]');if(form){const role=form.querySelector('#role'),group=form.querySelector('[data-creator-fields]'),organisation=form.querySelector('#organisation');const update=()=>{group.hidden=role.value!=='creator';organisation.required=role.value==='creator';};role.addEventListener('change',update);update();const pw=form.querySelector('#password'),confirm=form.querySelector('#password-confirm'),feedback=form.querySelector('[data-password-feedback]');const check=()=>{const mismatch=confirm.value!==''&&pw.value!==confirm.value;confirm.setCustomValidity(mismatch?'Passwords do not match.':'');feedback.textContent=mismatch?'Passwords do not match yet.':confirm.value?'Passwords match.':'';};pw.addEventListener('input',check);confirm.addEventListener('input',check);}
 document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
 document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
 document.querySelectorAll('[data-rail-next]').forEach(button=>button.addEventListener('click',()=>{document.getElementById(button.dataset.railNext).scrollBy({left:360,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}));
 if(location.pathname.endsWith('/login.php')){const m=location.hash.match(/^#token=(v1\.[0-9]+\.[0-9]+\.[0-9]+\.[a-f0-9]{64})$/);if(m){sessionStorage.setItem('fairboard-scan',m[1]);history.replaceState(null,'',location.pathname+location.search);}}
 const eventCoverForm=document.querySelector('[data-event-cover-form]');
 if(eventCoverForm){
  const file=eventCoverForm.querySelector('#artwork-file');
  const radio=eventCoverForm.querySelector('#artwork-upload-radio');
  const trigger=eventCoverForm.querySelector('[data-artwork-upload-trigger]');
  const preview=eventCoverForm.querySelector('[data-artwork-upload-preview]');
  const placeholder=eventCoverForm.querySelector('[data-artwork-upload-placeholder]');
  const hint=eventCoverForm.querySelector('[data-artwork-upload-hint]');
  const feedback=eventCoverForm.querySelector('[data-artwork-upload-feedback]');
  const cropButton=eventCoverForm.querySelector('[data-cover-crop-current]');
  const cropDialog=eventCoverForm.querySelector('[data-cover-crop-dialog]');
  const cropCanvas=eventCoverForm.querySelector('[data-cover-crop-canvas]');
  const cropContext=cropCanvas.getContext('2d');
  const zoomInput=eventCoverForm.querySelector('[data-cover-crop-zoom]');
  const zoomValue=eventCoverForm.querySelector('[data-cover-crop-zoom-value]');
  const cropStatus=eventCoverForm.querySelector('[data-cover-crop-status]');
  const applyCropButton=eventCoverForm.querySelector('[data-cover-crop-apply]');
  const cancelCropButton=eventCoverForm.querySelector('[data-cover-crop-cancel]');
  const ratioButtons=[...eventCoverForm.querySelectorAll('[data-crop-ratio]')];
  const defaultHelp=feedback.textContent;
  const initialPreview={src:preview.getAttribute('src')||'',hidden:preview.hidden};
  let committedFile=file.files&&file.files[0]||null;
  let committedPreview={...initialPreview};
  let cropImage=null;
  let pendingFile=null;
  let cropRatio=4/5;
  let zoom=1;
  let offsetX=0;
  let offsetY=0;
  let drag=null;
  let readSequence=0;

  function replaceInputFile(selected){
   if(!selected){file.value='';return true;}
   if(typeof DataTransfer==='undefined')return false;
   try{
    const transfer=new DataTransfer();
    transfer.items.add(selected);
    file.files=transfer.files;
    return file.files.length===1;
   }catch(error){return false;}
  }

  function renderPreview(state){
   if(state.src)preview.setAttribute('src',state.src);else preview.removeAttribute('src');
   preview.hidden=!!state.hidden;
   placeholder.hidden=!state.hidden;
   hint.textContent=state.hidden?'Choose image':'Change image';
   cropButton.hidden=state.hidden||!radio.checked;
  }

  function restoreCommitted(){
   if(!replaceInputFile(committedFile)){file.value='';committedFile=null;}
   renderPreview(committedPreview);
  }

  function updateRatioButtons(){
   ratioButtons.forEach(button=>{
    const [width,height]=button.dataset.cropRatio.split(':').map(Number);
    button.setAttribute('aria-pressed',String(Math.abs(width/height-cropRatio)<0.001));
   });
  }

  function setCropCanvasSize(){
   cropCanvas.width=600;
   cropCanvas.height=Math.round(cropCanvas.width/cropRatio);
  }

  function drawCrop(){
   if(!cropImage)return;
   const width=cropCanvas.width;
   const height=cropCanvas.height;
   const imageWidth=cropImage.naturalWidth;
   const imageHeight=cropImage.naturalHeight;
   const scale=Math.max(width/imageWidth,height/imageHeight)*zoom;
   const drawWidth=imageWidth*scale;
   const drawHeight=imageHeight*scale;
   const limitX=Math.max(0,(drawWidth-width)/2);
   const limitY=Math.max(0,(drawHeight-height)/2);
   offsetX=Math.max(-limitX,Math.min(limitX,offsetX));
   offsetY=Math.max(-limitY,Math.min(limitY,offsetY));
   const drawX=(width-drawWidth)/2+offsetX;
   const drawY=(height-drawHeight)/2+offsetY;
   cropContext.clearRect(0,0,width,height);
   cropContext.fillStyle='#111014';
   cropContext.fillRect(0,0,width,height);
   cropContext.imageSmoothingEnabled=true;
   cropContext.imageSmoothingQuality='high';
   cropContext.drawImage(cropImage,drawX,drawY,drawWidth,drawHeight);
  }

  function openCropEditor(image,sourceFile){
   cropImage=image;
   pendingFile=sourceFile;
   cropRatio=4/5;
   zoom=1;
   offsetX=0;
   offsetY=0;
   drag=null;
   zoomInput.value='1';
   zoomValue.textContent='1.0×';
   updateRatioButtons();
   setCropCanvasSize();
   drawCrop();
   cropStatus.textContent='Your original image will not be changed.';
   applyCropButton.disabled=false;
   try{cropDialog.showModal();}
   catch(error){cropImage=null;pendingFile=null;restoreCommitted();feedback.textContent='The image editor could not be opened in this browser.';}
  }

  function cancelCrop(){
   readSequence++;
   cropImage=null;
   pendingFile=null;
   drag=null;
   restoreCommitted();
   if(cropDialog.open)cropDialog.close();
  }

  function loadImageFile(selected){
   const sequence=++readSequence;
   const reader=new FileReader();
   reader.onload=()=>{
    if(sequence!==readSequence)return;
    if(typeof reader.result!=='string'){restoreCommitted();feedback.textContent='The selected image could not be read.';return;}
    const image=new Image();
    image.onload=()=>{
     if(sequence!==readSequence)return;
     if(image.naturalWidth<100||image.naturalHeight<100||image.naturalWidth>4096||image.naturalHeight>4096){
      restoreCommitted();
      feedback.textContent='Use an image between 100 and 4096 pixels on each side.';
      return;
     }
     openCropEditor(image,selected);
    };
    image.onerror=()=>{
     if(sequence!==readSequence)return;
     restoreCommitted();
     feedback.textContent='The selected image could not be opened.';
    };
    image.src=reader.result;
   };
   reader.onerror=()=>{
    if(sequence!==readSequence)return;
    restoreCommitted();
    feedback.textContent='The selected image could not be read.';
   };
   reader.readAsDataURL(selected);
  }

  function openCurrentPreviewForCrop(){
   if(preview.hidden||!preview.src)return;
   radio.checked=true;
   cropButton.hidden=true;
   const image=new Image();
   image.onload=()=>{
    if(image.naturalWidth<100||image.naturalHeight<100){feedback.textContent='This image is too small to crop.';cropButton.hidden=false;return;}
    const source=committedFile||new File([],'event-cover.jpg',{type:'image/jpeg'});
    openCropEditor(image,source);
   };
   image.onerror=()=>{feedback.textContent='The current image could not be opened for cropping.';cropButton.hidden=false;};
   image.src=preview.src;
  }

  async function applyCrop(){
   if(!cropImage||!pendingFile)return;
   applyCropButton.disabled=true;
   cropStatus.textContent='Preparing your cropped image…';
   try{
    const canvasWidth=cropCanvas.width;
    const canvasHeight=cropCanvas.height;
    const imageWidth=cropImage.naturalWidth;
    const imageHeight=cropImage.naturalHeight;
    const scale=Math.max(canvasWidth/imageWidth,canvasHeight/imageHeight)*zoom;
    const drawWidth=imageWidth*scale;
    const drawHeight=imageHeight*scale;
    const drawX=(canvasWidth-drawWidth)/2+offsetX;
    const drawY=(canvasHeight-drawHeight)/2+offsetY;
    const sourceX=Math.max(0,-drawX/scale);
    const sourceY=Math.max(0,-drawY/scale);
    const sourceWidth=Math.min(imageWidth-sourceX,canvasWidth/scale);
    const sourceHeight=Math.min(imageHeight-sourceY,canvasHeight/scale);

    const makeOutputCanvas=maxSide=>{
     const output=document.createElement('canvas');
     const outputWidth=cropRatio>=1?maxSide:Math.round(maxSide*cropRatio);
     const outputHeight=cropRatio>=1?Math.round(maxSide/cropRatio):maxSide;
     output.width=outputWidth;
     output.height=outputHeight;
     const context=output.getContext('2d');
     if(!context)throw new Error('Image editing is unavailable in this browser.');
     context.fillStyle='#fff';
     context.fillRect(0,0,outputWidth,outputHeight);
     context.imageSmoothingEnabled=true;
     context.imageSmoothingQuality='high';
     context.drawImage(cropImage,sourceX,sourceY,sourceWidth,sourceHeight,0,0,outputWidth,outputHeight);
     return output;
    };

    let output=makeOutputCanvas(1600);
    let blob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',0.86));
    if(!blob)throw new Error('The cropped image could not be prepared.');
    if(blob.size>4*1024*1024){
     output=makeOutputCanvas(1200);
     blob=await new Promise(resolve=>output.toBlob(resolve,'image/jpeg',0.78));
    }
    if(!blob||blob.size>4*1024*1024)throw new Error('The cropped image is still too large. Try a different image.');

    const baseName=(pendingFile.name||'event-cover').replace(/\.[^.]+$/,'').replace(/[^a-zA-Z0-9_-]/g,'-').slice(0,80)||'event-cover';
    const croppedFile=new File([blob],baseName+'-cropped.jpg',{type:'image/jpeg',lastModified:Date.now()});
    if(!replaceInputFile(croppedFile))throw new Error('Your browser cannot apply the crop. Please use a recent version of Chrome, Edge, Firefox or Safari.');

    committedFile=croppedFile;
    committedPreview={src:output.toDataURL('image/jpeg',0.86),hidden:false};
    renderPreview(committedPreview);
    feedback.textContent='Cropped image ready. It will be uploaded when you save the event.';
    pendingFile=null;
    cropImage=null;
    cropDialog.close();
   }catch(error){
    cropStatus.textContent=error.message||'The image could not be cropped. Please try again.';
    applyCropButton.disabled=false;
   }
  }

  if(!preview.hidden){placeholder.hidden=true;hint.textContent='Change image';}
  renderPreview(committedPreview);

  const chooseImage=()=>{radio.checked=true;renderPreview(committedPreview);file.click();};
  trigger.addEventListener('click',chooseImage);
  radio.addEventListener('click',()=>file.click());
  file.addEventListener('change',()=>{
   const selected=file.files&&file.files[0];
   if(!selected)return;
   radio.checked=true;
   cropButton.hidden=true;
   if(!['image/jpeg','image/png','image/webp'].includes(selected.type)||selected.size>4*1024*1024||selected.size===0){
    restoreCommitted();
    feedback.textContent='Choose a JPEG, PNG or WebP image up to 4 MB.';
    return;
   }
   loadImageFile(selected);
  });

  eventCoverForm.querySelectorAll('input[name="artwork"]:not([value="upload"])').forEach(choice=>choice.addEventListener('change',()=>{
   if(choice.checked){
    readSequence++;
    committedFile=null;
    file.value='';
    committedPreview={...initialPreview};
    renderPreview(committedPreview);
    feedback.textContent=defaultHelp;
   }
  }));

  cropButton.addEventListener('click',openCurrentPreviewForCrop);
  ratioButtons.forEach(button=>button.addEventListener('click',()=>{
   const [width,height]=button.dataset.cropRatio.split(':').map(Number);
   cropRatio=width/height;
   zoom=1;
   offsetX=0;
   offsetY=0;
   zoomInput.value='1';
   zoomValue.textContent='1.0×';
   updateRatioButtons();
   setCropCanvasSize();
   drawCrop();
  }));
  zoomInput.addEventListener('input',()=>{
   zoom=Number(zoomInput.value);
   zoomValue.textContent=zoom.toFixed(1)+'×';
   drawCrop();
  });
  cropCanvas.addEventListener('pointerdown',event=>{
   if(!cropImage)return;
   drag={x:event.clientX,y:event.clientY,offsetX,offsetY};
   cropCanvas.setPointerCapture(event.pointerId);
  });
  cropCanvas.addEventListener('pointermove',event=>{
   if(!drag)return;
   const rect=cropCanvas.getBoundingClientRect();
   offsetX=drag.offsetX+(event.clientX-drag.x)*(cropCanvas.width/rect.width);
   offsetY=drag.offsetY+(event.clientY-drag.y)*(cropCanvas.height/rect.height);
   drawCrop();
  });
  const stopDragging=event=>{
   if(!drag)return;
   drag=null;
   if(cropCanvas.hasPointerCapture&&cropCanvas.hasPointerCapture(event.pointerId))cropCanvas.releasePointerCapture(event.pointerId);
  };
  cropCanvas.addEventListener('pointerup',stopDragging);
  cropCanvas.addEventListener('pointercancel',()=>{drag=null;});
  applyCropButton.addEventListener('click',applyCrop);
  cancelCropButton.addEventListener('click',cancelCrop);
  cropDialog.addEventListener('cancel',event=>{event.preventDefault();cancelCrop();});
  cropDialog.addEventListener('click',event=>{if(event.target===cropDialog)cancelCrop();});
  eventCoverForm.addEventListener('submit',event=>{
   if(cropDialog.open){
    event.preventDefault();
    cropStatus.textContent='Choose “Use cropped image” or cancel before saving.';
   }
  });
 }
 const deletion=document.querySelector('[data-delete-account]');
 if(deletion){const field=deletion.querySelector('[name="confirmation"]'),button=deletion.querySelector('[data-delete-submit]');const update=()=>{button.disabled=field.value!=='Delete';};field.addEventListener('input',update);update();}
})();
