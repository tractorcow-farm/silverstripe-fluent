!function(){"use strict";var e={8:function(e){e.exports=NodeUrl},798:function(e,t,r){r.r(t),r.d(t,{default:function(){return I}});var n={};r.r(n),r.d(n,{exclude:function(){return E},extract:function(){return v},parse:function(){return k},parseUrl:function(){return O},pick:function(){return S},stringify:function(){return F},stringifyUrl:function(){return x}});const o="%[a-f0-9]{2}",c=new RegExp("("+o+")|([^%]+?)","gi"),s=new RegExp("("+o+")+","gi");function i(e,t){try{return[decodeURIComponent(e.join(""))]}catch{}if(1===e.length)return e;t=t||1;const r=e.slice(0,t),n=e.slice(t);return Array.prototype.concat.call([],i(r),i(n))}function a(e){try{return decodeURIComponent(e)}catch{let t=e.match(c)||[];for(let r=1;r<t.length;r++)t=(e=i(t,r).join("")).match(c)||[];return e}}function l(e){if("string"!=typeof e)throw new TypeError("Expected `encodedURI` to be of type `string`, got `"+typeof e+"`");try{return decodeURIComponent(e)}catch{return function(e){const t={"%FE%FF":"��","%FF%FE":"��"};let r=s.exec(e);for(;r;){try{t[r[0]]=decodeURIComponent(r[0])}catch{const e=a(r[0]);e!==r[0]&&(t[r[0]]=e)}r=s.exec(e)}t["%C2"]="�";const n=Object.keys(t);for(const r of n)e=e.replace(new RegExp(r,"g"),t[r]);return e}(e)}}function u(e,t){if("string"!=typeof e||"string"!=typeof t)throw new TypeError("Expected the arguments to be of type `string`");if(""===e||""===t)return[];const r=e.indexOf(t);return-1===r?[]:[e.slice(0,r),e.slice(r+t.length)]}function f(e,t){const r={};if(Array.isArray(t))for(const n of t){const t=Object.getOwnPropertyDescriptor(e,n);t?.enumerable&&Object.defineProperty(r,n,t)}else for(const n of Reflect.ownKeys(e)){const o=Object.getOwnPropertyDescriptor(e,n);if(o.enumerable){t(n,e[n],e)&&Object.defineProperty(r,n,o)}}return r}const p=e=>null==e,d=e=>encodeURIComponent(e).replace(/[!'()*]/g,(e=>`%${e.charCodeAt(0).toString(16).toUpperCase()}`)),m=Symbol("encodeFragmentIdentifier");function y(e){if("string"!=typeof e||1!==e.length)throw new TypeError("arrayFormatSeparator must be single character string")}function g(e,t){return t.encode?t.strict?d(e):encodeURIComponent(e):e}function h(e,t){return t.decode?l(e):e}function b(e){return Array.isArray(e)?e.sort():"object"==typeof e?b(Object.keys(e)).sort(((e,t)=>Number(e)-Number(t))).map((t=>e[t])):e}function w(e){const t=e.indexOf("#");return-1!==t&&(e=e.slice(0,t)),e}function j(e,t){return t.parseNumbers&&!Number.isNaN(Number(e))&&"string"==typeof e&&""!==e.trim()?e=Number(e):!t.parseBooleans||null===e||"true"!==e.toLowerCase()&&"false"!==e.toLowerCase()||(e="true"===e.toLowerCase()),e}function v(e){const t=(e=w(e)).indexOf("?");return-1===t?"":e.slice(t+1)}function k(e,t){y((t={decode:!0,sort:!0,arrayFormat:"none",arrayFormatSeparator:",",parseNumbers:!1,parseBooleans:!1,...t}).arrayFormatSeparator);const r=function(e){let t;switch(e.arrayFormat){case"index":return(e,r,n)=>{t=/\[(\d*)]$/.exec(e),e=e.replace(/\[\d*]$/,""),t?(void 0===n[e]&&(n[e]={}),n[e][t[1]]=r):n[e]=r};case"bracket":return(e,r,n)=>{t=/(\[])$/.exec(e),e=e.replace(/\[]$/,""),t?void 0!==n[e]?n[e]=[...n[e],r]:n[e]=[r]:n[e]=r};case"colon-list-separator":return(e,r,n)=>{t=/(:list)$/.exec(e),e=e.replace(/:list$/,""),t?void 0!==n[e]?n[e]=[...n[e],r]:n[e]=[r]:n[e]=r};case"comma":case"separator":return(t,r,n)=>{const o="string"==typeof r&&r.includes(e.arrayFormatSeparator),c="string"==typeof r&&!o&&h(r,e).includes(e.arrayFormatSeparator);r=c?h(r,e):r;const s=o||c?r.split(e.arrayFormatSeparator).map((t=>h(t,e))):null===r?r:h(r,e);n[t]=s};case"bracket-separator":return(t,r,n)=>{const o=/(\[])$/.test(t);if(t=t.replace(/\[]$/,""),!o)return void(n[t]=r?h(r,e):r);const c=null===r?[]:r.split(e.arrayFormatSeparator).map((t=>h(t,e)));void 0!==n[t]?n[t]=[...n[t],...c]:n[t]=c};default:return(e,t,r)=>{void 0!==r[e]?r[e]=[...[r[e]].flat(),t]:r[e]=t}}}(t),n=Object.create(null);if("string"!=typeof e)return n;if(!(e=e.trim().replace(/^[?#&]/,"")))return n;for(const o of e.split("&")){if(""===o)continue;const e=t.decode?o.replace(/\+/g," "):o;let[c,s]=u(e,"=");void 0===c&&(c=e),s=void 0===s?null:["comma","separator","bracket-separator"].includes(t.arrayFormat)?s:h(s,t),r(h(c,t),s,n)}for(const[e,r]of Object.entries(n))if("object"==typeof r&&null!==r)for(const[e,n]of Object.entries(r))r[e]=j(n,t);else n[e]=j(r,t);return!1===t.sort?n:(!0===t.sort?Object.keys(n).sort():Object.keys(n).sort(t.sort)).reduce(((e,t)=>{const r=n[t];return e[t]=Boolean(r)&&"object"==typeof r&&!Array.isArray(r)?b(r):r,e}),Object.create(null))}function F(e,t){if(!e)return"";y((t={encode:!0,strict:!0,arrayFormat:"none",arrayFormatSeparator:",",...t}).arrayFormatSeparator);const r=r=>t.skipNull&&p(e[r])||t.skipEmptyString&&""===e[r],n=function(e){switch(e.arrayFormat){case"index":return t=>(r,n)=>{const o=r.length;return void 0===n||e.skipNull&&null===n||e.skipEmptyString&&""===n?r:null===n?[...r,[g(t,e),"[",o,"]"].join("")]:[...r,[g(t,e),"[",g(o,e),"]=",g(n,e)].join("")]};case"bracket":return t=>(r,n)=>void 0===n||e.skipNull&&null===n||e.skipEmptyString&&""===n?r:null===n?[...r,[g(t,e),"[]"].join("")]:[...r,[g(t,e),"[]=",g(n,e)].join("")];case"colon-list-separator":return t=>(r,n)=>void 0===n||e.skipNull&&null===n||e.skipEmptyString&&""===n?r:null===n?[...r,[g(t,e),":list="].join("")]:[...r,[g(t,e),":list=",g(n,e)].join("")];case"comma":case"separator":case"bracket-separator":{const t="bracket-separator"===e.arrayFormat?"[]=":"=";return r=>(n,o)=>void 0===o||e.skipNull&&null===o||e.skipEmptyString&&""===o?n:(o=null===o?"":o,0===n.length?[[g(r,e),t,g(o,e)].join("")]:[[n,g(o,e)].join(e.arrayFormatSeparator)])}default:return t=>(r,n)=>void 0===n||e.skipNull&&null===n||e.skipEmptyString&&""===n?r:null===n?[...r,g(t,e)]:[...r,[g(t,e),"=",g(n,e)].join("")]}}(t),o={};for(const[t,n]of Object.entries(e))r(t)||(o[t]=n);const c=Object.keys(o);return!1!==t.sort&&c.sort(t.sort),c.map((r=>{const o=e[r];return void 0===o?"":null===o?g(r,t):Array.isArray(o)?0===o.length&&"bracket-separator"===t.arrayFormat?g(r,t)+"[]":o.reduce(n(r),[]).join("&"):g(r,t)+"="+g(o,t)})).filter((e=>e.length>0)).join("&")}function O(e,t){t={decode:!0,...t};let[r,n]=u(e,"#");return void 0===r&&(r=e),{url:r?.split("?")?.[0]??"",query:k(v(e),t),...t&&t.parseFragmentIdentifier&&n?{fragmentIdentifier:h(n,t)}:{}}}function x(e,t){t={encode:!0,strict:!0,[m]:!0,...t};const r=w(e.url).split("?")[0]||"";let n=F({...k(v(e.url),{sort:!1}),...e.query},t);n&&(n=`?${n}`);let o=function(e){let t="";const r=e.indexOf("#");return-1!==r&&(t=e.slice(r)),t}(e.url);if(e.fragmentIdentifier){const n=new URL(r);n.hash=e.fragmentIdentifier,o=t[m]?n.hash:`#${e.fragmentIdentifier}`}return`${r}${n}${o}`}function S(e,t,r){r={parseFragmentIdentifier:!0,[m]:!1,...r};const{url:n,query:o,fragmentIdentifier:c}=O(e,r);return x({url:n,query:f(o,t),fragmentIdentifier:c},r)}function E(e,t,r){return S(e,Array.isArray(t)?e=>!t.includes(e):(e,r)=>!t(e,r),r)}var I=n}},t={};function r(n){var o=t[n];if(void 0!==o)return o.exports;var c=t[n]={exports:{}};return e[n](c,c.exports,r),c.exports}r.d=function(e,t){for(var n in t)r.o(t,n)&&!r.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},r.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},r.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})};var n=c(r(8)),o=c(r(798));function c(e){return e&&e.__esModule?e:{default:e}}window.jQuery.entwine("ss",(e=>{const t=()=>{if(window&&window.ss&&window.ss.config&&window.ss.config.sections){const e=window.ss.config.sections.find((e=>"TractorCow\\Fluent\\Control\\LocaleAdmin"===e.name));if(e)return e.fluent||{}}return{}};e("input[data-hides]").entwine({onmatch(){this._super();const t=this.data("hides"),r=e(`[name='${t}']`).closest(".field");this.is(":checked")?r.hide():r.show()},onunmatch(){this._super()},onchange(){const t=this.data("hides"),r=e(`[name='${t}']`).closest(".field");this.is(":checked")?r.slideUp():r.slideDown()}}),e(".cms > .cms-container > .cms-menu > .cms-panel-content").entwine({onmatch(){this._super();const r=t();if(void 0===r.locales||0===r.locales.length)return;const n=e("<div class='cms-fluent-selector font-icon font-icon-caret-up-down'>\n          <select class='cms-fluent-selector-locales custom-select c-select'></select>\n        </div>");r.locales.forEach((t=>{const o=e("<option />").text(t.title).prop("value",t.code);t.code===r.locale&&o.prop("selected",!0),e("select",n).append(o)})),this.prepend(n)}}),e(".cms-fluent-selector").entwine({onclick(){this.toggleClass("active")}}),e(".cms-fluent-selector .cms-fluent-selector-locales").entwine({onchange(e){e.preventDefault();const r=this.val(),c=((e,r)=>{const c=t();if(!c.param)return e;const s=n.default.parse(e),i=o.default.parse(s.search);return i[c.param]=r,s.search=o.default.stringify(i),n.default.format(s)})(document.location.href,r);window.location.href=c}})}))}();