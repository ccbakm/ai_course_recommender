</div>

<script src="<?php echo url_path('/assets/neural-aurora.js') . '?v=' . time(); ?>"></script>
<script src="<?php echo url_path('/assets/app.js') . '?v=' . time(); ?>"></script>

<button id="SAVE_BTN_FINAL" style="position:fixed!important;bottom:30px!important;right:30px!important;padding:20px 40px!important;background:linear-gradient(90deg,#7c4dff,#00e5ff)!important;color:white!important;border:none!important;border-radius:12px!important;font-size:18px!important;font-weight:bold!important;cursor:pointer!important;z-index:999999!important;box-shadow:0 8px 30px rgba(124,77,255,0.8)!important;display:none!important">💾 SAVE PLAN</button>

<script>
// Global variable to store plan data
window.currentGeneratedPlan = null;

// Show button only when results are visible
setInterval(function(){
const rp=document.querySelector('.result-panel-fullscreen');
const btn=document.getElementById('SAVE_BTN_FINAL');
if(rp&&rp.classList.contains('show')){
btn.style.display='block';
}else{
btn.style.display='none';
}
},200);

// Intercept the plan data when it's rendered
// We need to hook into the renderResult function
const originalFetch = window.fetch;
window.fetch = function(...args) {
  const url = args[0];
  if (url && url.includes('recommend.php')) {
    return originalFetch.apply(this, args).then(response => {
      // Clone the response so we can read it
      const clonedResponse = response.clone();
      clonedResponse.json().then(data => {
        // Store the plan data globally
        console.log('Plan data captured:', data);
        window.currentGeneratedPlan = data;
      }).catch(err => {
        console.log('Could not parse plan data:', err);
      });
      return response;
    });
  }
  return originalFetch.apply(this, args);
};

// Save functionality
document.getElementById('SAVE_BTN_FINAL').onclick=async function(){
const n=prompt('Plan name:','My Learning Plan');
if(!n)return;

const i=document.getElementById('interests')?.value||'';
const l=document.getElementById('level')?.value||'Beginner';
const h=document.getElementById('hours')?.value||'6-8';
const g=document.getElementById('goal')?.value||'';

// Use the captured plan data or create a simple one
const planData = window.currentGeneratedPlan || {
  summary: 'Learning plan for ' + i,
  list: []
};

console.log('Saving plan with data:', planData);

try{
const r=await fetch('api/save-plan.php',{
  method:'POST',
  headers:{'Content-Type':'application/json'},
  body:JSON.stringify({
    title:n,
    domain:i,
    skill_level:l,
    weekly_hours:h,
    goal:g,
    plan_data:planData,
    is_active:true
  })
});

const d=await r.json();
if(d.success){
  alert('✓ Plan saved with ' + (planData.list ? planData.list.length : 0) + ' steps!');
  window.location.href='my-plans.php';
}
else if(d.auth_required){
  // STORE PLAN DATA BEFORE REDIRECTING TO LOGIN
  console.log('🔐 Login required - storing plan data in localStorage...');
  try {
    localStorage.setItem('pendingPlan', JSON.stringify({
      formData: {
        interests: i,
        level: l,
        hours: h,
        goal: g
      },
      planData: planData,
      timestamp: Date.now()
    }));
    console.log('✅ Plan data stored successfully!');
    console.log('📦 Stored data:', {interests: i, level: l, hours: h, goal: g, planData: planData});
  } catch (err) {
    console.error('❌ Error storing plan:', err);
  }
  
  if(confirm('Login required to save this plan. Your plan will be saved automatically after login.\n\nClick OK to login now.')) {
    window.location.href='login.php';
  }
}
else{
  alert('Error: '+(d.error||'Failed'));
}
}catch(e){
  alert('Error: '+e.message);
}
};
</script>

</body>
</html>