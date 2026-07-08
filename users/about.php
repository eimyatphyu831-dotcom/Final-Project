<?php
include '../includes/header.php';
?>

<!-- ANALYTICS & STUDIO BIOGRAPHY OVERVIEW (GEOMETRIC TRIPTYCH) -->
<section id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div
        class="bg-white rounded-[2.5rem] p-8 md:p-12 shadow-sm border border-slate-100 flex flex-col lg:flex-row items-center justify-between gap-12">

        <!-- Left Side Data Content Column -->
        <div class="max-w-md w-full">
            <h2 class="text-3xl font-serif font-bold text-brand-600 leading-tight">
                Creating Unforgettable Moments Since 2010
            </h2>
            <p class="text-sm text-slate-500 mt-4 leading-relaxed">
                Our dedicated design team focuses on every minor detailed element to craft custom environments that
                truly mirror your desired ambiance guidelines.
            </p>

            <!-- Statistical Analytics Layout -->
            <div class="flex gap-12 mt-8">
                <div>
                    <span class="text-3xl font-serif font-bold text-brand-900">500+</span>
                    <p class="text-xs font-medium text-slate-400 mt-1">Events Managed</p>
                </div>
                <div>
                    <span class="text-3xl font-serif font-bold text-brand-900">15+</span>
                    <p class="text-xs font-medium text-slate-400 mt-1">Years Experience</p>
                </div>
            </div>
        </div>

        <!-- Right Side Three Geometric Images Layout System -->
        <div class="w-full lg:w-1/2 grid grid-cols-3 gap-4 items-center justify-center min-h-[280px]">

            <!-- Shape 1: Perfect Circle Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-full overflow-hidden shadow-md border-4 border-slate-50/50 hover:scale-105 transition duration-300 animate-pulse"
                    style="animation-duration: 4s;">
                    <img src="https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&q=80&w=400"
                        alt="Circle Studio Frame" class="w-full h-full object-cover">
                </div>
            </div>

            <!-- Shape 2: Custom CSS Clip-Path Heart Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 overflow-hidden shadow-xl bg-transparent hover:scale-105 transition duration-300 animate-pulse"
                    style="
            mask-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22black%22><path d=%22M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z%22/></svg>');
            -webkit-mask-image: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22black%22><path d=%22M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z%22/></svg>');
            mask-size: cover;
            -webkit-mask-size: cover;
            mask-repeat: no-repeat;
            -webkit-mask-repeat: no-repeat;
            mask-position: center;
            -webkit-mask-position: center;
            animation-duration: 4s;
         ">
                    <img src="https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?auto=format&fit=crop&q=80&w=400"
                        alt="Heart Studio Frame" class="w-full h-full object-cover scale-110 object-center">
                </div>
            </div>

            <!-- Shape 3: Geometric Rounded Square Frame -->
            <div class="flex flex-col items-center justify-center">
                <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-[1.75rem] overflow-hidden shadow-md border-4 border-slate-50/50 hover:scale-105 transition duration-300 animate-pulse"
                    style="animation-duration: 4s;">
                    <img src="https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&q=80&w=400"
                        alt="Square Studio Frame" class="w-full h-full object-cover">
                </div>
            </div>

        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>