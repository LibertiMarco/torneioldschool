/* Photo controls live above the canvas, so they never appear in exported PNGs. */
window.PhotoTouchEditor = (() => {
  const states = new Map();
  const state = type => {
    if (!states.has(type)) states.set(type, {sx:1, sy:1, dx:0, dy:0, active:false});
    return states.get(type);
  };
  function transform(type, image, frame, rect) {
    const s = state(type);
    if (s.image !== image) Object.assign(s, {image, sx:1, sy:1, dx:0, dy:0});
    const box = {x:rect.x+s.dx, y:rect.y+s.dy, w:rect.w*s.sx, h:rect.h*s.sy};
    Object.assign(s, {frame, base:rect, box});
    refresh(s);
    return box;
  }
  function refresh(s) {
    if (!s.selection) return;
    s.layer.hidden = !s.active || !s.box;
    if (!s.box) return;
    const b=s.box, f=s.frame;
    const x=Math.max(f.x,b.x), y=Math.max(f.y,b.y);
    const right=Math.min(f.x+f.w,b.x+b.w), bottom=Math.min(f.y+f.h,b.y+b.h);
    s.selection.style.cssText=`position:absolute;left:${x/1080*100}%;top:${y/1350*100}%;width:${Math.max(0,right-x)/1080*100}%;height:${Math.max(0,bottom-y)/1350*100}%;border:2px solid #55dfff;box-sizing:border-box;touch-action:none;cursor:move;pointer-events:auto;`;
  }
  function init() {
    for (const type of ['ft','mvp']) {
      const s=state(type), canvas=document.getElementById(type==='ft'?'fulltimeCanvas':'mvpCanvas');
      const wrap=document.createElement('div');
      wrap.style.cssText='position:relative;width:min(100%,540px);margin:auto;';
      canvas.before(wrap);wrap.append(canvas);canvas.style.width='100%';canvas.style.maxHeight='none';
      const button=document.createElement('button');button.type='button';button.textContent='Modifica foto con touch';button.setAttribute('aria-pressed','false');
      const hint=document.createElement('p');hint.className='hint';hint.textContent='Trascina la foto per spostarla. Usa due dita per lo zoom, gli angoli per ridimensionare, i lati per modificare solo larghezza o altezza.';
      wrap.before(button,hint);
      const layer=document.createElement('div');layer.style.cssText='position:absolute;inset:0;overflow:hidden;pointer-events:none;';layer.hidden=true;
      const selection=document.createElement('div');selection.style.pointerEvents='auto';layer.append(selection);wrap.append(layer);Object.assign(s,{layer,selection});
      for (const [key,x,y] of [['nw',0,0],['n',50,0],['ne',100,0],['e',100,50],['se',100,100],['s',50,100],['sw',0,100],['w',0,50]]) {
        const handle=document.createElement('span');handle.dataset.handle=key;handle.style.cssText=`position:absolute;left:${x}%;top:${y}%;width:36px;height:36px;transform:translate(${x===0?0:x===100?-100:-50}%,${y===0?0:y===100?-100:-50}%);background:#55dfff;border:2px solid #08243b;border-radius:6px;box-sizing:border-box;touch-action:none;cursor:${key}-resize;pointer-events:auto;`;selection.append(handle);
      }
      const pointers=new Map();let gesture=null;
      const point=e=>{const r=canvas.getBoundingClientRect();return{x:(e.clientX-r.left)*1080/r.width,y:(e.clientY-r.top)*1350/r.height};};
      const stop=()=>{const ids=[...pointers.keys()];pointers.clear();gesture=null;for(const id of ids)if(selection.hasPointerCapture(id))selection.releasePointerCapture(id);};
      button.addEventListener('click',()=>{stop();s.active=!s.active;button.textContent=s.active?'Termina modifica foto':'Modifica foto con touch';button.setAttribute('aria-pressed',String(s.active));refresh(s);});
      selection.addEventListener('pointerdown',e=>{
        if(e.button>0||!s.box||pointers.size>=2)return;
        e.preventDefault();e.stopPropagation();pointers.set(e.pointerId,point(e));selection.setPointerCapture(e.pointerId);
        const points=[...pointers.values()];
        gesture={box:{...s.box},base:{...s.base},image:s.image,p:points[0],handle:e.target.dataset.handle||''};
        if(points.length===2)Object.assign(gesture,{handle:'pinch',distance:Math.max(1,Math.hypot(points[1].x-points[0].x,points[1].y-points[0].y)),center:{x:(points[0].x+points[1].x)/2,y:(points[0].y+points[1].y)/2}});
      });
      selection.addEventListener('pointermove',e=>{
        if(!gesture||!pointers.has(e.pointerId))return;
        if(s.image!==gesture.image){stop();return;}
        e.preventDefault();pointers.set(e.pointerId,point(e));
        const g=gesture,p=point(e),b={...g.box},dx=p.x-g.p.x,dy=p.y-g.p.y;
        if(g.handle==='pinch'){
          const [a,c]=[...pointers.values()],scale=Math.max(.05,Math.min(20,Math.hypot(c.x-a.x,c.y-a.y)/g.distance));
          b.w*=scale;b.h*=scale;b.x=(a.x+c.x)/2+(g.box.x-g.center.x)*scale;b.y=(a.y+c.y)/2+(g.box.y-g.center.y)*scale;
        }else if(g.handle){
          if(g.handle.includes('e'))b.w=Math.max(20,Math.min(21600,b.w+dx));
          if(g.handle.includes('s'))b.h=Math.max(20,Math.min(27000,b.h+dy));
          if(g.handle.includes('w')){b.w=Math.max(20,Math.min(21600,g.box.w-dx));b.x=g.box.x+g.box.w-b.w;}
          if(g.handle.includes('n')){b.h=Math.max(20,Math.min(27000,g.box.h-dy));b.y=g.box.y+g.box.h-b.h;}
        }else{b.x+=dx;b.y+=dy;}
        Object.assign(s,{sx:b.w/g.base.w,sy:b.h/g.base.h,dx:b.x-g.base.x,dy:b.y-g.base.y});
        drawAll();
      });
      for(const event of ['pointerup','pointercancel','lostpointercapture'])selection.addEventListener(event,stop);
      refresh(s);
    }
  }
  function sync(type,image) {
    const s=state(type);
    if(!image)s.box=null;
    refresh(s);
  }
  return {init,transform,sync};
})();
