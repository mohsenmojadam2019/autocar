/** Escapes text before inserting API-driven labels into suggestion markup. */
const esc=(value='')=>String(value).replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));

/** Boots storefront mega-menu, autocomplete, vehicle picker, gallery and admin navigation without Alpine/Livewire/Vite. */
document.addEventListener('DOMContentLoaded',()=>{
    document.documentElement.classList.add('js-ready');
    const trigger=document.querySelector('[data-mega-trigger]'); const mega=document.querySelector('[data-mega-menu]');
    if(trigger&&mega){trigger.addEventListener('click',()=>{mega.hidden=!mega.hidden;trigger.setAttribute('aria-expanded',String(!mega.hidden));}); document.addEventListener('click',event=>{if(!mega.hidden&&!mega.contains(event.target)&&!trigger.contains(event.target)){mega.hidden=true;trigger.setAttribute('aria-expanded','false');}});}

    const input=document.querySelector('[data-search-input]'); const box=document.querySelector('[data-search-suggestions]'); let timer;
    if(input&&box){input.addEventListener('input',()=>{clearTimeout(timer); const q=input.value.trim(); if(q.length<2){box.hidden=true;return;} timer=setTimeout(async()=>{try{const response=await fetch(`/api/v1/search/suggest?q=${encodeURIComponent(q)}`,{headers:{Accept:'application/json'}}); if(!response.ok)throw new Error('search'); const json=await response.json(); box.innerHTML=(json.data||[]).map(item=>`<a href="/product/${esc(item.slug)}"><span>${esc(item.name)}<small> ${esc(item.sku)}</small></span><b>${Number(item.sale_price||0).toLocaleString('fa-IR')}</b></a>`).join(''); box.hidden=!json.data?.length;}catch{box.hidden=true;}},220);});}

    const picker=document.querySelector('[data-vehicle-picker]');
    if(picker){const make=picker.querySelector('[data-vehicle-make]'),model=picker.querySelector('[data-vehicle-model]'),generation=picker.querySelector('[data-vehicle-generation]'),trim=picker.querySelector('[data-vehicle-trim]'); const fill=(element,items,label,format=item=>item.name)=>{element.innerHTML=`<option value="">${label}</option>`+items.map(item=>`<option value="${item.id}">${esc(format(item))}</option>`).join('');element.disabled=false;}; make?.addEventListener('change',async()=>{model.disabled=generation.disabled=trim.disabled=true;if(!make.value)return;const response=await fetch(`/api/v1/vehicles/makes/${make.value}/models`);if(!response.ok)return;const json=await response.json();fill(model,json.data,'مدل');}); model?.addEventListener('change',async()=>{generation.disabled=trim.disabled=true;if(!model.value)return;const response=await fetch(`/api/v1/vehicles/models/${model.value}/generations`);if(!response.ok)return;const json=await response.json();fill(generation,json.data,'نسل',item=>`${item.name} ${item.from_year??''}-${item.to_year??''}`);}); generation?.addEventListener('change',async()=>{trim.disabled=true;if(!generation.value)return;const response=await fetch(`/api/v1/vehicles/generations/${generation.value}/trims`);if(!response.ok)return;const json=await response.json();fill(trim,json.data,'سال / تیپ',item=>`${item.year} - ${item.name}`);});}

    document.querySelectorAll('[data-gallery-thumb]').forEach(button=>button.addEventListener('click',()=>{const main=document.querySelector('[data-main-image]');if(main)main.src=button.dataset.src;}));
    const adminButton=document.querySelector('[data-admin-menu]'),sidebar=document.querySelector('[data-admin-sidebar]'); if(adminButton&&sidebar)adminButton.addEventListener('click',()=>sidebar.classList.toggle('open'));
});
