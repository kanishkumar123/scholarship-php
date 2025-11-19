let currentPage = 1;
const totalPages = 3;

document.addEventListener('DOMContentLoaded', () => {
    showPage(1);

    // Top Nav Tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const page = parseInt(tab.dataset.page);
            showPage(page);
        });
    });

    // Institution -> Course
    const institution = document.getElementById('institution_name');
    const courseSelect = document.getElementById('course');
    institution.addEventListener('change', () => {
        const collegeId = institution.value;
        courseSelect.innerHTML = '<option value="">-- SELECT COURSE --</option>';
        if (collegeId && collegePrograms[collegeId]) {
            collegePrograms[collegeId].programs.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                courseSelect.appendChild(opt);
            });
            courseSelect.disabled = false;
        } else courseSelect.disabled = true;
    });

    // Year -> Semester
    document.getElementById("year_of_study").addEventListener('change', () => {
        const year = parseInt(document.getElementById("year_of_study").value);
        const semSelect = document.getElementById("semester");
        semSelect.innerHTML = '<option value="">-- Select Semester --</option>';
        if(year){
            for(let i=(year-1)*2+1;i<=(year-1)*2+2;i++){
                const opt=document.createElement("option");
                opt.value=i; opt.textContent=i;
                semSelect.appendChild(opt);
            }
        }
    });
});

function showPage(page){
    document.querySelectorAll(".page").forEach(p=>p.classList.remove("active"));
    document.getElementById("page"+page).classList.add("active");
    currentPage=page;

    // Nav Buttons
    document.getElementById("prevBtn").style.display= page>1 ? "block" : "none";
    document.getElementById("nextBtn").style.display= page<totalPages ? "block" : "none";
    document.getElementById("submitBtn").style.display= page===totalPages ? "block" : "none";

    // Top tabs active
    document.querySelectorAll('.nav-tab').forEach(t=>t.classList.remove('active'));
    document.querySelector(`.nav-tab[data-page='${page}']`)?.classList.add('active');
}

function nextPage(){ if(validatePage(currentPage)) showPage(currentPage+1); }
function prevPage(){ showPage(currentPage-1); }

function showModal(msg){
    const modal=document.getElementById("errorModal");
    document.getElementById("errorMessage").innerHTML=msg;
    modal.style.display="flex";
}
function closeModal(){ document.getElementById("errorModal").style.display="none"; }

function validatePage(page){
    let isValid=true, firstError=null;
    const checkField=(id,msg)=>{
        const f=document.getElementById(id);
        if(!f || f.value.trim()===""){
            showModal(msg);
            if(!firstError) firstError=f;
            isValid=false;
        }
    };
    if(page===1){
        [
            ["institution_name","Please select Institution Name."],
            ["course","Please select Course of Study."],
            ["year_of_study","Please select Year of Study."],
            ["semester","Please select Semester."],
            ["gender","Please select Gender."],
            ["father_name","Please enter Father's Name."],
            ["mother_name","Please enter Mother's Name."],
            ["community","Please select Community."],
            ["caste","Please enter Caste."],
            ["family_income","Please enter Annual Family Income."],
            ["address","Please enter Permanent Address."],
            ["phone_std","Please enter Phone No."],
            ["mobile","Please enter Mobile Number."],
            ["email","Please enter Email ID."]
        ].forEach(f=>checkField(f[0],f[1]));
    }
    if(page===3){
        if(document.getElementById("disabled_yes")?.checked)
            checkField("disability_category","Please specify disability category.");
        if(document.getElementById("parent_vmrf_yes")?.checked)
            checkField("parent_vmrf_details","Please provide parent working details.");
    }
    if(firstError) firstError.focus();
    return isValid;
}

