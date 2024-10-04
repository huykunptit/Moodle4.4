// Declare variables
let filterCourse = $('#filterAll').val();
let sortBy = $('#sortBy').val();
let search = "";
let type = $("#viewType").val();
const container = $('div[role="main"]');
var urlExport = Moodle.url + `/grade/report/learning_results/export.php?filterCourse=${filterCourse}&sortBy=${sortBy}&search=${search}&type=${type}`;


// Update filterCourse and send POST request on change
$('#filterAll').change(function() {
    filterCourse = $(this).val();
    setTimeout(() => {
        postdata("POST");
    }, 300);
    urlExport = Moodle.url + `/grade/report/learning_results/export.php?filterCourse=${filterCourse}&sortBy=${sortBy}&search=${search}&type=${type}`;
    $("#ExportFile").attr("href", urlExport);
});

$('#viewType').change(function(){
    type = $(this).val();
    setTimeout(() => {
        postdata("POST");
    }, 300);
    urlExport = Moodle.url + `/grade/report/learning_results/export.php?filterCourse=${filterCourse}&sortBy=${sortBy}&search=${search}&type=${type}`;
    $("#ExportFile").attr("href", urlExport);
})
// Update search and send POST request on input
$('#searchInput').on('input', function() {
    search = $(this).val();
    setTimeout(() => {
        postdata("POST");
    }, 300);
    urlExport = Moodle.url + `/grade/report/learning_results/export.php?filterCourse=${filterCourse}&sortBy=${sortBy}&search=${search}&type=${type}`;
    $("#ExportFile").attr("href", urlExport);
});

// Update sortBy and send POST request on change
$('#sortBy').change(function() {
    sortBy = $(this).val();
    setTimeout(() => {
        postdata("POST");
    }, 300);
    urlExport = Moodle.url + `/grade/report/learning_results/export.php?filterCourse=${filterCourse}&sortBy=${sortBy}&search=${search}&type=${type}`;
    $("#ExportFile").attr("href", urlExport);
});


function postdata(method = "POST") {
    $.ajax({
        url: Moodle.url + '/grade/report/learning_results/ajax.php', 
        method: method,
        data: {
            filterCourse: filterCourse,
            sortBy: sortBy,
            search: search,
            type: type,
            actions: 'sort',
        },
        dataType: 'json',
        success: function(res) {

            if (res && typeof res === 'string') {
                container.find("#table-data").remove();
                container.append(res);
            } else {
                console.error('Invalid response format:', res);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
        }
    });
}


document.getElementById('ExportFile').addEventListener('click', function(e) {
    e.preventDefault(); // Ngăn chặn hành động mặc định

    // Tạo URL với các tham số
    const baseUrl =  Moodle.url + '/grade/report/learning_results/export.php';
    const params = new URLSearchParams({
        filterCourse: filterCourse,
        sortBy: sortBy,
        search: search,
        type: type
    }).toString();

    const fullUrl = `${baseUrl}?${params}`;

    // Thay đổi href của link và điều hướng đến URL mới
    window.location.href = fullUrl;
});