const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000
});

const flasherror = $('#notice_error').val();
const flashsuccess = $('#notice_success').val();
const flashwarning = $('#notice_warning').val();

if(flasherror){
    flash_notice('error', flasherror);
}
if(flashsuccess){
    flash_notice('success', flashsuccess);
}
if(flashwarning){
    flash_notice('warning', flashwarning);
}

function flash_notice(type, message) {
    Toast.fire({
        type: type,
        title: message
    })
}