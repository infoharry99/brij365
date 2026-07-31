import React from 'react';
import { createRoot } from 'react-dom/client';

const originalCreateElement = React.createElement;

function cleanBuilder360UiText(value) {
  if (typeof value !== 'string' || value.length === 0) {
    return value;
  }

  return value
    .replace(/\blaravel[-_]auth\b/gi, 'secured account')
    .replace(/\blaravel[-_]sqlite\b/gi, 'current records')
    .replace(/\blaravel[-_][a-z0-9_ -]+\b/gi, 'managed process')
    .replace(/\bLaravel\s*\+\s*Blade\s*\+\s*Vite\b/gi, 'Builder360 workspace')
    .replace(/\bLaravel\/Vite\b/gi, 'Builder360')
    .replace(/\bLaravel-backed\b/gi, 'available')
    .replace(/\bLaravel-scoped\b/gi, 'available')
    .replace(/\bLaravel connected\b/gi, 'Connected')
    .replace(/\bLaravel\b/gi, 'Builder360')
    .replace(/\bMySQL-backed\b/gi, 'live')
    .replace(/\bMySQL records\b/gi, 'current records')
    .replace(/\bMySQL metrics\b/gi, 'live metrics')
    .replace(/\bMySQL\b/gi, 'current records')
    .replace(/\bSQLite\b/gi, 'current records')
    .replace(/\bReact\b/gi, 'Builder360')
    .replace(/\bVite\b/gi, 'Builder360')
    .replace(/\bBlade\b/gi, 'Builder360')
    .replace(/\bPHP\b/gi, 'application')
    .replace(/\blocalStorage\b/gi, 'saved workspace')
    .replace(/\bserver-backed\b/gi, 'live')
    .replace(/\blive live metrics\b/gi, 'live metrics')
    .replace(/\blive live records\b/gi, 'current records')
    .replace(/\bbackend-backed\b/gi, 'available')
    .replace(/\bbackend\b/gi, 'system')
    .replace(/\bfrontend\b/gi, 'user interface')
    .replace(/\bAPI REQUIRED\b/gi, 'SETUP INCOMPLETE')
    .replace(/\bAPI required\b/gi, 'Setup incomplete')
    .replace(/\bAPI\b/gi, 'system')
    .replace(/\bbootstrap payload\b/gi, 'startup information')
    .replace(/\bbootstrap payloads\b/gi, 'startup information')
    .replace(/\bbootstrap\b/gi, 'startup')
    .replace(/\bpayloads\b/gi, 'information')
    .replace(/\bpayload\b/gi, 'information')
    .replace(/\bdatabase-backed\b/gi, 'record-based')
    .replace(/\bdatabase backed\b/gi, 'record-based')
    .replace(/\bdatabase records\b/gi, 'current records')
    .replace(/\bdatabase forecast\b/gi, 'current forecast')
    .replace(/\bdatabase project average\b/gi, 'portfolio project average')
    .replace(/\bdatabase\b/gi, 'records')
    .replace(/\bThe records has no\b/gi, 'No')
    .replace(/\bsession driver\b/gi, 'session setting')
    .replace(/\bSameSite\b/gi, 'browser setting')
    .replace(/\bsecure cookie\b/gi, 'secure session')
    .replace(/\bCSRF\b/gi, 'request protection')
    .replace(/\bComposer\b/gi, 'Builder360')
    .replace(/\bNode\b/gi, 'Builder360')
    .replace(/\bNPM\b/gi, 'Builder360')
    .replace(/\bRedis\b/gi, 'cache service')
    .replace(/\bS3\b/gi, 'document storage')
    .replace(/\bWebuzo\b/gi, 'hosting panel')
    .replace(/\bUbuntu\b/gi, 'server')
    .replace(/\bVPS\b/gi, 'server')
    .replace(/\bgoverned\b/gi, 'managed')
    .replace(/\bgovernance\b/gi, 'management')
    .replace(/\bgovernance[._-]backup[._-]dr\b/gi, 'Backup and Recovery')
    .replace(/\brole[- ]scoped\b/gi, 'available to you')
    .replace(/\brole_scoped\b/gi, 'available to you')
    .replace(/\bserver scoped\b/gi, 'available')
    .replace(/\bserver-scoped\b/gi, 'available')
    .replace(/\bserver_scoped\b/gi, 'available')
    .replace(/\bcustomer[- ]scoped\b/gi, 'available to you')
    .replace(/\bcustomer_scoped\b/gi, 'available to you')
    .replace(/\bpartner[- ]scoped\b/gi, 'available to you')
    .replace(/\bpartner_scoped\b/gi, 'available to you')
    .replace(/\bemployee[- ]scoped\b/gi, 'available to you')
    .replace(/\bemployee_scoped\b/gi, 'available to you')
    .replace(/\bcompany[- ]scoped\b/gi, 'company-level')
    .replace(/\bcompany_scoped\b/gi, 'company-level')
    .replace(/\bcompany scope\b/gi, 'selected company')
    .replace(/\bglobal scope\b/gi, 'all access')
    .replace(/\bcurrent scope\b/gi, 'selected view')
    .replace(/\bscoped\b/gi, 'available')
    .replace(/\bscope\b/gi, 'view')
    .replace(/\bconfiguration\b/gi, 'settings')
    .replace(/\bconfigured\b/gi, 'set up')
    .replace(/\bpermission matrix\b/gi, 'access list')
    .replace(/\bpermissions\b/gi, 'access')
    .replace(/\bpermission\b/gi, 'access')
    .replace(/\bauthorization\b/gi, 'access')
    .replace(/\bauthorized\b/gi, 'allowed')
    .replace(/\bunauthorized\b/gi, 'not allowed')
    .replace(/\bvalidation\b/gi, 'checks')
    .replace(/\bvalidated\b/gi, 'checked')
    .replace(/\bworkflow engine\b/gi, 'approval process')
    .replace(/\baudit-backed\b/gi, 'with activity history')
    .replace(/\baudit trail\b/gi, 'activity history')
    .replace(/\baudit history\b/gi, 'activity history')
    .replace(/\baudit events\b/gi, 'activity records')
    .replace(/\baudit log\b/gi, 'activity log')
    .replace(/\bprototype notice\b/gi, 'note')
    .replace(/\bprototype_notice\b/gi, 'note')
    .replace(/\bprototype\b/gi, 'sample')
    .replace(/\bdemo\b/gi, 'sample')
    .replace(/\bseeded\b/gi, 'sample')
    .replace(/\bdatabase_seed\b/gi, 'sample setup')
    .replace(/\blocal_qa_seed\b/gi, 'test setup')
    .replace(/\blocal QA\b/gi, 'test')
    .replace(/\blocal[_-]?qa\b/gi, 'test')
    .replace(/\bdiagnostic\b/gi, 'status')
    .replace(/\breadiness\b/gi, 'status')
    .replace(/\bsystem-backed\b/gi, 'available')
    .replace(/\bsystem connection\b/gi, 'system')
    .replace(/\bDATA SETUP REQUIRED\b/gi, 'SETUP INCOMPLETE')
    .replace(/\bData setup required\b/gi, 'Setup incomplete')
    .replace(/\blive records scoped\b/gi, 'current records')
    .replace(/\bcurrent records scoped\b/gi, 'current records')
    .replace(/\bsetup incomplete required\b/gi, 'Setup incomplete')
    .replace(/\bNo local sample\b/gi, 'No')
    .replace(/\bNo local\b/gi, 'No')
    .replace(/\bnot simulated\b/gi, 'not available')
    .replace(/\bsimulated internally\b/gi, 'handled internally');
}

function cleanBuilder360ReactValue(value) {
  if (typeof value === 'string') {
    return cleanBuilder360UiText(value);
  }
  if (Array.isArray(value)) {
    return value.map(cleanBuilder360ReactValue);
  }
  return value;
}

function cleanBuilder360ReactProps(props) {
  if (!props || typeof props !== 'object') {
    return props;
  }

  const displayProps = [
    'title',
    'sub',
    'label',
    'placeholder',
    'aria-label',
    'alt',
    'empty',
    'message',
    'children',
  ];
  let next = props;

  for (const key of displayProps) {
    if (Object.prototype.hasOwnProperty.call(props, key)) {
      if (next === props) {
        next = { ...props };
      }
      next[key] = cleanBuilder360ReactValue(props[key]);
    }
  }

  return next;
}

React.createElement = function builder360CreateElement(type, props, ...children) {
  return originalCreateElement(
    type,
    cleanBuilder360ReactProps(props),
    ...children.map(cleanBuilder360ReactValue),
  );
};

window.React = React;
window.ReactDOM = { createRoot };
window.Builder360CleanUiText = cleanBuilder360UiText;

function loadBuilder360Bootstrap() {
  const element = document.getElementById('builder360-bootstrap');

  if (!element) {
    window.Builder360Server = null;
    return;
  }

  try {
    window.Builder360Server = JSON.parse(element.textContent || 'null');
  } catch (error) {
    window.Builder360Server = null;
    console.error('[Builder360] Startup information could not be parsed', error);
  }
}

async function bootBuilder360() {
  loadBuilder360Bootstrap();

  await import('./legacy/000-e073cd66-bc6a-4aca-bcd1-f40c97158b33.jsx');
  await import('./legacy/001-5c291a20-0063-4865-a863-e0923a25dff2.js');
  await import('./legacy/002-e78c7379-36a4-4aba-9f01-3b187f9404e1.js');
  await import('./legacy/003-d8ad24a2-283a-4185-bd52-11f60915cbd8.jsx');
  await import('./legacy/004-df054ee0-7345-442e-8c13-9abc4d2271b8.jsx');
  await import('./legacy/005-d8d4c3ba-894a-41a1-bedf-73983a89301c.jsx');
  await import('./legacy/006-5f60363e-5b6e-492d-aaae-4366c704c7d7.jsx');
  await import('./legacy/007-abf65512-bc59-4a7a-abfb-6c9af028f74f.jsx');
  await import('./legacy/008-63a8d810-41af-4168-9c6a-7e47b902f7c8.jsx');
  await import('./legacy/009-92e26222-d787-41ec-8aad-0a1a17a5daea.jsx');
  await import('./legacy/010-0597b85c-2572-4093-8f66-6b17d8448f25.jsx');
  await import('./legacy/011-973c8f4b-cead-4977-8558-c06816cec1b7.jsx');
  await import('./legacy/012-2fd217a4-02c1-4690-9a5a-e99883aacdef.jsx');
  await import('./legacy/013-6148766d-8004-44fb-b694-783028f3da90.jsx');
  await import('./legacy/014-f93aab6b-6f95-4976-8c9c-f375a5a6b76c.jsx');
  await import('./legacy/015-bbaa254d-76a5-4498-b54c-b4abcfcb07c4.jsx');
  await import('./legacy/016-1c906ee2-63fe-48cd-8a5d-9dc79c1b3913.jsx');
  await import('./legacy/017-2106d610-773e-43da-8805-92b5fd2724e0.jsx');
  await import('./legacy/018-4067eeab-5fac-4b9d-95b9-1d41ae0444ca.jsx');
  await import('./legacy/019-9b9b3735-d303-42d3-b8bb-64a67c45794d.jsx');
  await import('./legacy/020-fd24a7fb-8616-4979-b954-f39429927e65.jsx');
  await import('./legacy/021-e3b7570a-8a61-409d-a5cd-9d68f1738d0e.jsx');
  await import('./legacy/022-5309489b-e0b7-4e1f-9d9f-ad3dddbc70d4.jsx');
  await import('./legacy/023-20035819-6b4e-4260-97f8-fbc2c6ccf347.js');
  await import('./legacy/024-ebd1b6ba-f254-4515-8e32-0a2eab22dde5.jsx');
  await import('./legacy/025-e2a1a850-ec0b-43ba-a084-e7c71ecc2b00.jsx');
  await import('./legacy/026-a1b28c04-3b3b-4719-b679-64ab724b68ce.js');
  await import('./legacy/027-8150ffc4-2956-4e2b-9a3f-a0e47ad4f0e9.jsx');
  await import('./legacy/028-4edf159b-aa6c-4df5-843c-a2659f1f307b.jsx');
  await import('./legacy/029-883c5200-73c4-4588-b9dc-28a51cbf22b4.jsx');
  await import('./legacy/030-eb182cc5-f3e2-445c-a1cd-e0aee08c4cdf.jsx');
  await import('./legacy/031-9ee1dc06-a285-4fa2-a225-8b5ccf4d4142.jsx');
  await import('./legacy/032-e7cb0ad9-ce79-4b77-b482-e77114366a47.js');
  await import('./legacy/033-13acd7c2-126a-43cf-8548-1163b24efcc9.jsx');
  await import('./legacy/034-f1269a6c-4021-45a8-bab9-429e7875b20e.jsx');
  await import('./legacy/035-e399a6c5-b138-430b-a3a9-70c5b335a784.jsx');
  await import('./legacy/036-a3563a9f-6298-4e2a-a48f-0f0e125e2863.jsx');
  await import('./legacy/037-6f9c7c4e-42a1-4cf1-9a9d-builder360hrms.jsx');
  await import('./legacy/038-67006742-4e44-48ac-906b-23036dce1ab6.jsx');

  if (typeof window.__BOOT__ !== 'function') {
    throw new Error('Builder360 boot function was not registered.');
  }

  window.__BOOT__();
}

bootBuilder360().catch((error) => {
  console.error('[Builder360] Application boot failed', error);
  const root = document.getElementById('root');
  if (root) {
    root.innerHTML = '<div style="padding:24px;font-family:system-ui,sans-serif;color:#991b1b"><strong>Builder360 failed to load.</strong><br/>Check the browser console for details.</div>';
  }
});
