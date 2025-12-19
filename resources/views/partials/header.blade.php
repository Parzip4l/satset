<!-- Begin Header -->
<header class="app-header" id="appHeader">
    <div class="container-fluid w-100">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <div class="d-inline-flex align-items-center gap-5">
                    <a href="index" class="fs-18 fw-semibold">
                        <img height="30" class="pe-app-sidebar-logo-default d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
                        <img height="30" class="pe-app-sidebar-logo-light d-none" alt="Logo" src="{{ asset('assets/images/logo-lrtj.png') }}">
                        <img height="30" class="pe-app-sidebar-logo-minimize d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
                        <img height="30" class="pe-app-sidebar-logo-minimize-light d-none" alt="Logo" src="{{ asset('assets/images/lrtj.png') }}">
                    </a>
                    <button type="button" class="vertical-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill" id="toggleSidebar">
                        <i class="bi bi-arrow-bar-left header-icon"></i>
                    </button>
                    <button type="button" class="horizontal-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill d-none" id="toggleHorizontal">
                        <i class="ri-menu-2-line header-icon"></i>
                    </button>
                    <div class="header-dropdown d-flex align-items-center">
                        
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 d-flex align-items-center gap-1">
                
                <div class="dark-mode-btn" id="toggleMode">
                    <button class="btn header-btn active" id="lightModeBtn">
                        <i class="bi bi-brightness-high"></i>
                    </button>
                    <button class="btn header-btn" id="darkModeBtn">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </div>
                <div class="dropdown pe-dropdown-mega d-none d-md-block">
                    <button class="btn header-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                    </button>
                    <div class="dropdown-menu dropdown-mega-md header-dropdown-menu pe-noti-dropdown-menu p-0">
                        <div class="p-3 border-bottom">
                            <h6 class="d-flex align-items-center mb-0">Notification <span class="badge bg-success rounded-circle align-middle ms-1">4</span></h6>
                        </div>
                        <div class="p-3">
                            <div class="noti-item">
                                <img src="assets/images/logo-md.png" alt="Logo Image" class="avatar-md">
                                <div>
                                    <a href="javascript:void(0)" class="stretched-link">
                                        <h6 class="mb-1">Item Back in Stock</h6>
                                    </a>
                                    <p class="text-muted mb-2">Today, 02:45 PM</p>
                                    <div class="p-2 bg-body-tertiary bg-opacity-50 rounded">
                                        <h6 class="mb-0 lh-base">Good news! The item you wanted is back in stock. Grab it before it’s gone again!</h6>
                                    </div>
                                </div>
                                <a href="javascript:void(0)" class="position-absolute top-10 end-0 fs-18 z-1 link link-danger"><i class="bi bi-x"></i></a>
                            </div>
                            
                        </div>
                    </div>
                </div>
                
                <div class="dropdown pe-dropdown-mega d-none d-md-block">
                    <button class="header-profile-btn btn gap-1 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="header-btn btn position-relative">
                            <img src="assets/images/avatar/avatar-10.jpg" alt="Avatar Image" class="img-fluid rounded-circle">
                            <span class="position-absolute translate-middle badge border border-light rounded-circle bg-success"><span class="visually-hidden">unread messages</span></span>
                        </span>
                        <div class="d-none d-lg-block pe-2">
                            <span class="d-block mb-0 fs-13 fw-semibold">{{ auth()->check() ? auth()->user()->name : 'Guest' }}</span>
                        </div>
                    </button>
                    <div class="dropdown-menu dropdown-mega-sm header-dropdown-menu p-3">
                        <div class="border-bottom pb-2 mb-2 d-flex align-items-center gap-2">
                            <img src="assets/images/avatar/avatar-10.jpg" alt="Avatar Image" class="avatar-md">
                            <div>
                                <a href="javascript:void(0)">
                                    <h6 class="mb-0 lh-base">{{ auth()->check() ? auth()->user()->name : 'Guest' }}</h6>
                                </a>
                                <p class="mb-0 fs-13 text-muted">{{ auth()->check() ? auth()->user()->email : 'Guest' }}</p>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-1 border-bottom pb-1">
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-person me-1"></i> View Profile</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-gear me-1"></i> Settings</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-award me-1"></i> Subscription</a></li>
                        </ul>
                        <ul class="list-unstyled mb-1 border-bottom pb-1">
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-clock me-1"></i> ChangLog</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-people me-1"></i> Team</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-headset me-1"></i> Support</a></li>
                        </ul>
                        <ul class="list-unstyled mb-0">
                            <li><a class="dropdown-item" href="javascript:void(0)"><i class="bi bi-box-arrow-right me-1"></i> Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- END Header -->

<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 bg-transparent">
            <div class="d-flex justify-content-between align-items-center bg-body">
                <div class="d-flex align-items-center border-0 px-3">
                    <i class="bi bi-search me-2"></i>
                    <input class="d-flex w-full py-3 bg-transparent border-0 focus-ring" placeholder="Search Here.." autocomplete="off" autocorrect="off" spellcheck="false" aria-autocomplete="list" role="combobox" aria-expanded="true" type="text">
                </div>
                <button type="button" class="btn-close pe-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-body mt-4">
                <p class="font-normal mb-2">Searching For...</p>
                <span class="badge bg-light-subtle border text-body">Analytics <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Project <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Eccomerce <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">CRM <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Logistics <i class="ri-close-line"></i></span>
                <span class="badge bg-light-subtle border text-body">Academy <i class="ri-close-line"></i></span>
            </div>
        </div>
    </div>
</div>