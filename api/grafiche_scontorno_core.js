/* Subject detection and mask composition; shared by the worker and tests. */
'use strict';
const GraphicsCutoutCore = (() => {
  const clamp = value => Math.max(0, Math.min(1, value));
  function overlap(a, b) {
    const intersection = Math.max(0, Math.min(a.right,b.right)-Math.max(a.left,b.left)) *
      Math.max(0, Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top));
    return intersection / (a.area+b.area-intersection || 1);
  }
  function selectPeople(logits, boxes, count, classes, subjects = 'main') {
    const people = [];
    for (let i = 0; i < count; i++) {
      const person = logits[i*classes], score = 1/(1+Math.exp(-person));
      if (score < .5 || !Number.isFinite(score)) continue;
      let strongest = true;
      for (let c = 1; c < classes; c++) if (logits[i*classes+c] > person) {strongest=false;break;}
      if (!strongest) continue;
      const [cx,cy,w,h] = boxes.subarray(i*4,i*4+4);
      if (![cx,cy,w,h].every(Number.isFinite) || w<=0 || h<=0) continue;
      const left=clamp(cx-w/2),right=clamp(cx+w/2),top=clamp(cy-h/2),bottom=clamp(cy+h/2);
      const area=(right-left)*(bottom-top);
      if (area>0) people.push({left,right,top,bottom,area,score});
    }
    people.sort((a,b)=>b.score-a.score);
    const unique=[];
    for (const person of people) if (!unique.some(other=>overlap(person,other)>.75)) unique.push(person);
    if (!unique.length) throw new Error('Non è stato possibile riconoscere una persona. Prova una foto più ravvicinata.');
    const largest=Math.max(...unique.map(person=>person.area));
    return {
      foreground:unique.filter(person=>subjects==='all'||person.area>=largest*.25),
      background:unique.filter(person=>subjects!=='all'&&person.area<largest*.25)
    };
  }
  function resizeRGB(rgb, width, height, side) {
    const output=new Float32Array(3*side*side),size=width*height;
    for(let y=0;y<side;y++)for(let x=0;x<side;x++) {
      const sx=Math.max(0,(x+.5)*width/side-.5),sy=Math.max(0,(y+.5)*height/side-.5);
      const x0=Math.floor(sx),y0=Math.floor(sy),x1=Math.min(width-1,x0+1),y1=Math.min(height-1,y0+1),fx=sx-x0,fy=sy-y0;
      for(let c=0;c<3;c++)output[c*side*side+y*side+x]=
        (rgb[c*size+y0*width+x0]*(1-fx)+rgb[c*size+y0*width+x1]*fx)*(1-fy)+
        (rgb[c*size+y1*width+x0]*(1-fx)+rgb[c*size+y1*width+x1]*fx)*fy;
    }
    return output;
  }
  function composeMatte(detail, foreground, background, width, height, radius=5, people=[]) {
    const size=width*height;
    if (detail.length!==size||foreground.length!==size||background.length!==size) throw new Error('Maschere di ritaglio non valide.');
    const horizontal=new Uint8Array(size),output=new Float32Array(size);
    const support=people.length?new Uint8Array(foreground):foreground;
    const recoverable=people.length?new Uint8Array(size):null;
    // Recover confidently opaque clothing within a detected subject's box.
    // Coarse object masks can miss dark fabric at the edge of the photograph.
    for(const person of people) {
      for(let y=Math.max(0,Math.floor(person.top*height));y<Math.min(height,Math.ceil(person.bottom*height));y++) {
        for(let x=Math.max(0,Math.floor(person.left*width));x<Math.min(width,Math.ceil(person.right*width));x++) {
          const p=y*width+x;if(detail[p]>=.98&&!background[p])recoverable[p]=1;
        }
      }
    }
    if(recoverable) {
      // Recovery must grow from the selected figure. Isolated confident
      // fragments inside its bounding box can belong to a background player.
      const queue=new Int32Array(size);let head=0,tail=0;
      for(let p=0;p<size;p++)if(support[p]&&recoverable[p]){recoverable[p]=0;queue[tail++]=p;}
      while(head<tail) {
        const p=queue[head++],x=p%width,y=Math.floor(p/width);
        for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++) {
          if((!dx&&!dy)||x+dx<0||x+dx>=width||y+dy<0||y+dy>=height)continue;
          const q=p+dy*width+dx;
          if(recoverable[q]){recoverable[q]=0;support[q]=1;queue[tail++]=q;}
        }
      }
    }
    // Recover fine edges just outside SAM's object boundary using the matting
    // model. Background instances remain excluded even inside this margin.
    for(let y=0;y<height;y++)for(let x=0;x<width;x++) {
      for(let dx=Math.max(0,x-radius);dx<=Math.min(width-1,x+radius);dx++)if(support[y*width+dx]){horizontal[y*width+x]=1;break;}
    }
    for(let y=0;y<height;y++)for(let x=0;x<width;x++) {
      const p=y*width+x;
      if(background[p])continue;
      for(let dy=Math.max(0,y-radius);dy<=Math.min(height-1,y+radius);dy++)if(horizontal[dy*width+x]) {
        output[p]=Number.isFinite(detail[p])?clamp(detail[p]):0;break;
      }
    }
    return output;
  }
  return {selectPeople,resizeRGB,composeMatte};
})();
