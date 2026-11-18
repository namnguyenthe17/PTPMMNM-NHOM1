// Client-side preview only; server validates actual upload.
(() => {
  const file=document.getElementById('ff-avatar-file');
  const target=document.getElementById('ff-avatar-preview');
  if (!file || !target) return;
  let previous=null;
  file.addEventListener('change', () => {
    const image=file.files?.[0];
    if (previous) {URL.revokeObjectURL(previous); previous=null;}
    if (!image || !['image/jpeg','image/png','image/webp'].includes(image.type)) return;
    if (image.size>2*1024*1024) {
      file.setCustomValidity('Ảnh tối đa 2 MB');file.reportValidity();return;
    }
    file.setCustomValidity('');
    previous=URL.createObjectURL(image);
    target.replaceChildren();
    const preview=document.createElement('img');
    preview.className='ff-avatar-img';preview.alt='Xem trước ảnh đại diện';preview.src=previous;
    target.append(preview);
  });
})();
