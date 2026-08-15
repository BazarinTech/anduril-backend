        <!-- Start Sidebar -->
        <nav class="sidebar lg:z-[9] z-[10] group-data-[sidebar=brand]/item:border-sky-600 dark:border-darkborder">
            <div class="h-full bg-white dark:bg-darklight group-data-[sidebar=dark]/item:bg-darklight group-data-[sidebar=brand]/item:bg-sky-600">
                <div class="p-4">
                    <a href="index-2.html" class="w-full main-logo">
                        <img src="assets/images/logo-dark.svg" class="mx-auto dark-logo h-7 logo dark:hidden group-data-[sidebar=dark]/item:hidden group-data-[sidebar=brand]/item:hidden" alt="logo" />
                        <img src="assets/images/logo-light.svg" class="hidden mx-auto light-logo h-7 logo dark:block group-data-[sidebar=dark]/item:block group-data-[sidebar=brand]/item:block" alt="logo" />
                        <img src="assets/images/logo-icon.svg" class="hidden mx-auto logo-icon h-7" alt="">
                    </a>
                </div>
                <div class="h-[calc(100vh-60px)]  overflow-y-auto overflow-x-hidden px-5 pb-4 space-y-16 detached-menu">
                    <ul class="relative flex flex-col gap-1 " x-data="sidebarMenu">
                        <h2 class="my-2 text-sm text-slate-500 group-data-[sidebar=dark]/item:text-slate-500 group-data-[sidebar=brand]/item:text-sky-200"><span>Main</span></h2>
                        <li class="menu nav-item">
                            <a href="dashboard" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="life-buoy" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Dashboard</span>
                                </div>
                            </a>
                        </li>
                        <h2 class="my-2 text-sm text-slate-500 group-data-[sidebar=dark]/item:text-slate-500 group-data-[sidebar=brand]/item:text-sky-200"><span>Generals</span></h2>
                        <li class="menu nav-item">
                            <a href="users" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="user" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Users</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="wallets" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="stop-circle" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Wallets</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="orders" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="codepen" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Orders</span>
                                </div>
                            </a>
                        </li>
                        <h2 class="my-2 text-sm text-slate-500 group-data-[sidebar=dark]/item:text-slate-500 group-data-[sidebar=brand]/item:text-sky-200"><span>Commodities Control</span></h2>
                        <li class="menu nav-item">
                            <a href="products" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="cpu" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Products</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="bonus" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="gift" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Bonus</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="incentives" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="cpu" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Incentives</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="incentive-application" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="stop-circle" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Incentives Requests</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="coupon" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="codepen" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Coupon</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item">
                            <a href="user-reward" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="gift" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">User Reward</span>
                                </div>
                            </a>
                        </li>
                        <h2 class="my-2 <?= $isFinance ? '' : 'hidden' ?> text-sm text-slate-500 group-data-[sidebar=dark]/item:text-slate-500 group-data-[sidebar=brand]/item:text-sky-200"><span>Transactions And Payments</span></h2>
                        <li class="menu nav-item <?= $isFinance ? '' : 'hidden' ?>">
                            <a href="transactions" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="file-text" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Transactions</span>
                                </div>
                            </a>
                        </li>
                        <li class="menu nav-item main-menu <?= $isFinance ? '' : 'hidden' ?>">
                            <a href="javascript:void(0);" class="items-center justify-between text-black nav-link group" :class="{'active' : isActive('components')}" @click="toggle('components', false)">
                                <div class="flex items-center">
                                    <i data-feather="box" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Topup Wallets</span>
                                </div>
                                <div class="flex items-center justify-center w-4 h-4 dropdown-icon" :class="{'!rotate-180' : isActive('components')}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6">
                                        <path d="M11.9997 13.1714L16.9495 8.22168L18.3637 9.63589L11.9997 15.9999L5.63574 9.63589L7.04996 8.22168L11.9997 13.1714Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                            </a>
                            <ul x-cloak x-show="isActive('components')" x-collapse class="flex flex-col gap-1 text-black sub-menu dark:text-white/60 group-data-[sidebar=dark]/item:text-white/60 group-data-[sidebar=brand]/item:text-sky-200">
                                <li><a href="payment-wallet">Payment Wallet</a></li>
                                <li><a href="service-wallet">Service Wallet</a></li>
                            </ul>
                        </li>
                        
                         <li class="menu nav-item main-menu <?= $isFinance ? '' : 'hidden' ?>">
                            <a href="javascript:void(0);" class="items-center justify-between text-black nav-link group" :class="{'active' : isActive('component1')}" @click="toggle('component1', false)">
                                <div class="flex items-center">
                                    <i data-feather="box" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Approve Transactions</span>
                                </div>
                                <div class="flex items-center justify-center w-4 h-4 dropdown-icon" :class="{'!rotate-180' : isActive('components')}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6">
                                        <path d="M11.9997 13.1714L16.9495 8.22168L18.3637 9.63589L11.9997 15.9999L5.63574 9.63589L7.04996 8.22168L11.9997 13.1714Z" fill="currentColor"></path>
                                    </svg>
                                </div>
                            </a>
                            <ul x-cloak x-show="isActive('component1')" x-collapse class="flex flex-col gap-1 text-black sub-menu dark:text-white/60 group-data-[sidebar=dark]/item:text-white/60 group-data-[sidebar=brand]/item:text-sky-200">
                                <li><a href="approve-withdrawals">Withdrawals</a></li>
                                <li><a href="approve-deposits">Deposits</a></li>
                            </ul>
                            
                        </li>
                        
                        <li class="menu nav-item <?= $isFinance ? '' : 'hidden' ?>">
                            <a href="platform-control" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="sliders" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Platform Control</span>
                                </div>
                            </a>
                        </li>
                        <h2 class="my-2 text-sm text-slate-500 group-data-[sidebar=dark]/item:text-slate-500 group-data-[sidebar=brand]/item:text-sky-200 <?= $isView ? '' : 'hidden' ?>"><span>Mangement</span></h2>
                        <li class="menu nav-item <?= $isView ? '' : 'hidden' ?>">
                            <a href="admins" class="nav-link group" @click="toggle('single' , false)">
                                <div class="flex items-center">
                                    <i data-feather="user-plus" class="size-4"></i>
                                    <span class="ltr:pl-1.5 rtl:pr-1.5">Admins</span>
                                </div>
                            </a>
                        </li>

                    </ul>
                </div>
            </div>
        </nav>
