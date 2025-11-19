<?php
session_start();
include("config.php");

// --- 1. CHECK IF ALREADY LOGGED IN ---
if (isset($_SESSION['student_id'])) {
    header("Location: student_form.php");
    exit;
}

$error = '';
$show_modal = false;
$modal_message = "Invalid Application No or DOB! Please contact your Nodal Officer if this persists.";

if (isset($_POST['login'])) {
    $app_no = $_POST['application_no'];
    $dob = $_POST['dob'];

    if (empty($app_no) || empty($dob)) {
        $error = "Application No and DOB are required.";
    } else {
        // --- 2. CRITICAL SECURITY FIX: Use Prepared Statements ---
        $stmt = $conn->prepare("SELECT * FROM scholarship_students WHERE application_no = ? AND dob = ?");
        $stmt->bind_param("ss", $app_no, $dob);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // --- 3. SUCCESS: SET SESSIONS AND REDIRECT ---
            $student = $result->fetch_assoc();
            
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['scholarship_id'] = $student['scholarship_id'];
            $_SESSION['student_name'] = $student['name'];

            header("Location: student_form.php");
            exit;
        } else {
            // --- 4. FAILURE: Show Error in Panel ---
            $error = "Invalid Application No or DOB!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VMRF Scholarship Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="admin/css/landing_style.css">
</head>
<body>

    <div class="login-panel-overlay" id="login-overlay"></div>
    <div class="login-panel" id="login-panel">
        <button type="button" class="login-panel-close" id="login-close-btn">&times;</button>
        
        <div class="login-box">
            <form method="post" action="index.php" class="login-form">
                
                <h3 class="university-title">
                    <span>VINAYAKA MISSIONS RESEARCH FOUNDATION</span>
                </h3>

                <h2>Student Portal</h2>
                <p>Login to view or complete your application.</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <input type="text" id="application_no" name="application_no" placeholder="" required autocomplete="off"
                           readonly onfocus="this.removeAttribute('readonly');">
                    <label for="application_no">Application No:</label>
                    <div class="input-bg"></div>
                </div>

                <div class="form-group">
                    <input type="date" id="dob" name="dob" placeholder="" required
                           readonly onfocus="this.removeAttribute('readonly');">
                    <label for="dob">Date of Birth:</label>
                    <div class="input-bg"></div>
                </div>
                
                <button type="submit" name="login" class="login-btn">Login</button>
                
                <div class="social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter (X)"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
                
                <div class="form-footer">
                    <a href="admin/login.php">
                        <i class="fa-solid fa-user-shield"></i> Are you an Admin?
                    </a>
                </div>
            </form>
        </div>
    </div>


    <header class="site-header">
        <div class="logo">
            <a href="#home">Scholar<span class="text-primary">Portal</span></a>
        </div>
        
        <button type="button" class="mobile-nav-toggle" id="mobile-nav-toggle" aria-label="Toggle navigation">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" id="site-nav">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#categories">Scholarships</a>
            <a href="#procedure">Procedure</a>
            <button type="button" class="login-nav-btn" id="login-open-btn">
                <i class="fa-solid fa-user-graduate"></i> Student Login
            </button>
        </nav>
    </header>

    <section class="hero-section" id="home" style="background-image: url('assets/scholar1.jpg');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h2 class="card-header scroll-animate">
                <span class="l">V</span><span class="l">M</span><span class="l">R</span><span class="l">F</span>
                <span class="l">S</span><span class="l">C</span><span class="l">H</span><span class="l">O</span><span class="l">L</span><span class="l">A</span><span class="l">R</span><span class="l">S</span><span class="l">H</span><span class="l">I</span><span class="l">P</span><span class="l">S</span>
            </h2>
            <p class="scroll-animate" style="animation-delay: 0.2s;">Rewarding Merit. Empowering Futures.</p>
            <a href="#about" class="hero-cta scroll-animate" style="animation-delay: 0.4s;">Learn More</a>
        </div>
        <div class="scroll-down-prompt">
            <i class="fa-solid fa-arrow-down"></i>
        </div>
    </section>

    <main class="content-wrapper">
        
        <section id="about" class="feature-section">
            <div class="feature-content scroll-animate">
                <h2 class="scene-title">Our Mission & Vision</h2>
                <p>Vinayaka Mission’s Research Foundation (Deemed to be University) offers Institutional Scholarships, Fee concessions, and Freeships to recognize and reward meritorious students. These are given every year to help young aspiring students pursue higher education and advanced studies.</p>
                <p>This aims with the mission and vision of VMRF-DU to provide opportunities to deserving candidates to undertake advanced studies and research. The quantum and number of scholarships are subject to change.</p>
            </div>
            <div class="feature-image-wrapper scroll-animate" style="animation-delay: 0.2s;">
                <img src="assets/scholar2.jpg" alt="University Campus">
            </div>
        </section>

        <section id="categories" class="content-scene">
            <h2 class="scene-title scroll-animate">Scholarship Categories</h2>
            <p class="scene-subtitle scroll-animate">Fee concession is provided to students based on the following categories.</p>
            
            <div class="category-grid">
                <div class="category-card scroll-animate">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    <h3>Founder’s Scholarship</h3>
                    <p>Freeship for top ranks in JEE, CBSE, District Toppers, State Board Toppers, and Orphan Candidates.</p>
                </div>
                <div class="category-card scroll-animate" style="animation-delay: 0.1s;">
                    <i class="fa-solid fa-star"></i>
                    <h3>Merit Scholarship</h3>
                    <p>Based on Qualifying Examination marks. Includes "Academic Excellence" and "In Pursuit of Excellence" awards.</p>
                </div>
                <div class="category-card scroll-animate" style="animation-delay: 0.2s;">
                    <i class="fa-solid fa-female"></i>
                    <h3>Shero Scholarship</h3>
                    <p>A dedicated scholarship for the empowerment of women, championed by Dr. Anuradha.</p>
                </div>
                <div class="category-card scroll-animate" style="animation-delay: 0s;">
                    <i class="fa-solid fa-users"></i>
                    <h3>Community & Need</h3>
                    <p>For socially backward, economically backward, first-generation, and single-parent students.</p>
                </div>
                <div class="category-card scroll-animate" style="animation-delay: 0.1s;">
                    <i class="fa-solid fa-wheelchair"></i>
                    <h3>Special Categories</h3>
                    <p>For wards of Ex-Servicemen, differently-abled students, and students with proficiency in sports.</p>
                </div>
                <div class="category-card scroll-animate" style="animation-delay: 0.2s;">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <h3>Affiliation</h3>
                    <p>For children of VMRF-DU staff, alumni, wards of alumni, and research scholars.</p>
                </div>
            </div>
        </section>
        
        <section class="image-break scroll-animate" style="background-image: url('assets/scholar4.jpg');">
            <div class="image-break-overlay">
                <h3>A Tradition of Excellence.</h3>
            </div>
        </section>

        <section id="procedure" class="content-scene">
            <h2 class="scene-title scroll-animate">Application Procedure</h2>
            <div class="procedure-wrapper">
                
                <div class="procedure-step scroll-animate">
                    <span>1</span>
                    <div class="procedure-text">
                        <h3>Selection & Application</h3>
                        <p>All eligible students must apply for fresh or renewal scholarships using the prescribed form. A student may apply for and avail ONLY ONE scholarship scheme. The scholarship committee will scrutinize and recommend eligible candidates.</p>
                    </div>
                </div>
                
                <div class="procedure-step scroll-animate" style="animation-delay: 0.1s;">
                    <span>2</span>
                    <div class="procedure-text">
                        <h3>Guidelines for Renewal</h3>
                        <p>All scholarships are renewed annually, subject to academic performance (above 9.0 SGPA without any break of study), discipline, and maintenance of at least 75% attendance. Students not previously on scholarship may apply for a 20% tuition fee waiver if they achieve above 9.0 SGPA.</p>
                    </div>
                </div>

                <div class="requirements-grid">
                    <div class="requirements-card positive scroll-animate">
                        <h4><i class="fa-solid fa-check-circle"></i> Stay Eligible</h4>
                        <ul>
                            <li>Maintain 9.0+ SGPA for renewal.</li>
                            <li>Maintain at least 75% attendance.</li>
                            <li>Uphold all university discipline rules.</li>
                        </ul>
                    </div>
                    <div class="requirements-card negative scroll-animate" style="animation-delay: 0.1s;">
                        <h4><i class="fa-solid fa-times-circle"></i> Ineligibility</h4>
                        <ul>
                            <li>Any break of study.</li>
                            <li>Disciplinary action, enquiry, or suspension.</li>
                            <li>Failure to apply by the stipulated deadline.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        
    </main>

    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-column">
                <h4>ScholarPortal</h4>
                <p>Empowering the next generation of leaders through accessible education and merit recognition.</p>
                <div class="social-icons-footer">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter (X)"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-column links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About VMRF</a></li>
                    <li><a href="#categories">Scholarships</a></li>
                    <li><a href="#procedure">Procedure</a></li>
                    <li><a href="admin/login.php">Admin Login</a></li>
                </ul>
            </div>
            <div class="footer-column links">
                <h4>Contact Us</h4>
                <ul>
                    <li><i class="fa-solid fa-map-marker-alt"></i>NH47, Sankari Main Road, Salem, Tamil Nadu 636308</li>
                    <li><i class="fa-solid fa-phone"></i> +91 82484 80352</li>
                    <li><i class="fa-solid fa-envelope"></i>director.admissions@vmu.edu.in</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>Copyright © <?php echo date("Y"); ?> Vinayaka Mission’s Research Foundation. All rights reserved.</p>
            <br></br>
            <p>Developed by Digital Team - VMRF</p>
        </div>

    </footer>


    <div id="nodalOfficerModal" class="modal-overlay" style="<?= $show_modal ? 'display:flex' : 'display:none' ?>">
        <div class="modal-content">
            <h3>Login Error</h3>
            <p><?php echo htmlspecialchars($modal_message); ?></p>
            <button class="modal-close-btn" id="modal-close-btn">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- 1. Login Panel Logic ---
            const loginOpenBtn = document.getElementById('login-open-btn');
            const loginCloseBtn = document.getElementById('login-close-btn');
            const loginPanel = document.getElementById('login-panel');
            const loginOverlay = document.getElementById('login-overlay');

            const openLogin = (e) => {
                if(e) e.preventDefault();
                loginPanel.classList.add('is-open');
                loginOverlay.classList.add('is-open');
                document.body.classList.remove('mobile-nav-open'); // Close nav if open
            };

            const closeLogin = () => {
                loginPanel.classList.remove('is-open');
                loginOverlay.classList.remove('is-open');
            };

            if (loginOpenBtn) loginOpenBtn.addEventListener('click', openLogin);
            if (loginCloseBtn) loginCloseBtn.addEventListener('click', closeLogin);
            if (loginOverlay) loginOverlay.addEventListener('click', closeLogin);

            // --- 2. Mobile Navigation Logic ---
            const mobileToggleBtn = document.getElementById('mobile-nav-toggle');
            const siteNav = document.getElementById('site-nav');

            if (mobileToggleBtn) {
                mobileToggleBtn.addEventListener('click', () => {
                    document.body.classList.toggle('mobile-nav-open');
                    // We removed the icon-swapping logic
                    // The CSS now handles the 'X' animation
                    closeLogin(); // Close login panel if open
                });
            }
            
            // Close mobile nav when a link is clicked
            if (siteNav) {
                siteNav.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        document.body.classList.remove('mobile-nav-open');
                        // We removed the icon-reset logic
                    });
                });
            }


            // --- 3. Header Scroll Effect ---
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    document.body.classList.add('scrolled');
                } else {
                    document.body.classList.remove('scrolled');
                }
            }, { passive: true });


            // --- 4. Scroll Animation Observer ---
            const scrollElements = document.querySelectorAll(".scroll-animate");
            if ("IntersectionObserver" in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("is-visible");
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });

                scrollElements.forEach((el) => observer.observe(el));
            } else {
                // Fallback for very old browsers
                scrollElements.forEach((el) => el.classList.add("is-visible"));
            }


            // --- 5. Modal Logic (from your original code) ---
            const nodalModal = document.getElementById('nodalOfficerModal');
            const modalCloseBtn = document.getElementById('modal-close-btn');
            
            function closeModal() {
                if (nodalModal) nodalModal.style.display = 'none';
            }
            
            if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);

            <?php if ($show_modal): ?>
                // If there was a PHP login error, open the modal
                if (nodalModal) nodalModal.style.display = 'flex';
            <?php endif; ?>
            
            <?php if (!empty($error) && !$show_modal): ?>
                // If there was a simple error (like "fields required"), open the slide-in panel
                openLogin();
            <?php endif; ?>

        });
    </script>
    <script src="https://astra.wati.io/widget/astra.js" id="6f3cb247-104a-4ec5-853a-b757be841a0e" data-mode="widget" defer></script>
</body>
</html>