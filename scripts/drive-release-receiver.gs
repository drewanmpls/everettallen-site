// Everett Allen Photography: files signed model releases into Google Drive.
// Paste this into a new Google Apps Script project (script.google.com), set SECRET, then
// Deploy > New deployment > type "Web app" > Execute as: Me > Who has access: Anyone.
// Copy the web app address into the site admin (Settings).

const SECRET = 'CHANGE-THIS-TO-A-LONG-RANDOM-WORD';
const EAP_FOLDER_ID = '0B6tyBsEA4Q06dWZibGVKXzBNRWs';   // your "EAP" folder
const SUBFOLDER_NAME = 'Model Releases';                 // created inside EAP the first time

function doPost(e) {
  try {
    const d = JSON.parse(e.postData.contents);
    if (d.secret !== SECRET) return out({ ok: false, error: 'Wrong secret word' });
    const parent = DriveApp.getFolderById(EAP_FOLDER_ID);
    const it = parent.getFoldersByName(SUBFOLDER_NAME);
    const folder = it.hasNext() ? it.next() : parent.createFolder(SUBFOLDER_NAME);
    const name = String(d.filename || 'release.pdf').replace(/[^\w.\- ]/g, '_');
    const old = folder.getFilesByName(name);            // an updated release replaces the earlier copy
    while (old.hasNext()) old.next().setTrashed(true);
    const blob = Utilities.newBlob(Utilities.base64Decode(d.pdf_base64), 'application/pdf', name);
    const file = folder.createFile(blob);
    return out({ ok: true, url: file.getUrl() });
  } catch (err) {
    return out({ ok: false, error: String(err) });
  }
}

function out(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}
