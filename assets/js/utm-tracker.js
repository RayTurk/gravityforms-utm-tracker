document.addEventListener('DOMContentLoaded', function() {
  console.log('UTM Tracker initialized');

  // Get UTM parameters from URL
  const params = new URLSearchParams(window.location.search);
  const utmParams = {};

  // Define UTM parameters to track
  const utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'term', 'content'];

  utmFields.forEach(param => {
      if (params.has(param)) {
          utmParams[param] = params.get(param);
          // Store in session storage
          sessionStorage.setItem(param, params.get(param));
          console.log(`Found UTM parameter ${param}:`, params.get(param));
      } else {
          // Try to get from session storage
          const stored = sessionStorage.getItem(param);
          if (stored) {
              utmParams[param] = stored;
              console.log(`Retrieved ${param} from session storage:`, stored);
          }
      }
  });

  // Find and fill all UTM fields
  const forms = document.querySelectorAll('.gform_wrapper form');
  console.log('Found Gravity Forms:', forms.length);

  forms.forEach((form, index) => {
      console.log(`Processing form ${index + 1}`);
      Object.keys(utmParams).forEach(param => {
          const fieldWrapper = form.querySelector(`.utm_param_${param}`);
          if (fieldWrapper) {
              const input = fieldWrapper.querySelector('input');
              if (input) {
                  input.value = utmParams[param];
                  console.log(`Set ${param} value to:`, utmParams[param]);
              } else {
                  console.log(`Input not found for ${param}`);
              }
          } else {
              console.log(`Field wrapper not found for ${param}`);
          }
      });
  });
});