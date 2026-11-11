const assert=require('assert');
Array.prototype.at=Array.prototype.at||function(i){return this[i<0?this.length+i:i]};
const b=require('../tornei/cup-bracket.js');
const seeds=b.selectSeeds([{teams:Array.from({length:8},(_,i)=>({nome:'Team '+(i+1)}))}],[{key:'gold',start:1,spots:8}],'GOLD');
const g={fase:'GOLD',fase_round:'QUARTI',fase_leg:'UNICA',squadra_casa:'Team 8',squadra_ospite:'Team 1',gol_casa:2,gol_ospite:2,giocata:1};
const next=extra=>b.build(seeds,[Object.assign({},g,extra)],'GOLD').find(r=>r.bracket_slot==='S1').squadra_casa;
assert(next({}).includes('Team 1'));
assert(next({decisa_rigori:1,rigori_casa:5,rigori_ospite:4}).includes('Team 8'));
assert.equal(next({fase_leg:'ANDATA'}),'Vincente Q1');
assert.equal(next({giocata:0}),'Vincente Q1');
assert(next({gol_casa:3}).includes('Team 8'));
const projected=b.build(seeds,[g],'GOLD');
assert.equal(b.winner([projected[0]],'Vincitrice').name,'Team 1');
assert.equal(b.winner([g],'Vincitrice'),null);
console.log('Passed: ranking tiebreak, penalties, pending return, unplayed match, score priority, final winner and missing ranking.');

