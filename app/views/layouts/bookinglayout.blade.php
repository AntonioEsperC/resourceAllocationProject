<!DOCTYPE HTML>
<html>
    <head>
        @include('includes.headBooking')
        @include('includes.header')
    </head>
    
    <body>
        <div class="container">
            <div id="resources" class="row">
                @yield('content')
            </div>
            <footer class="row">
                @include('includes.footer')
            </footer>
        </div>        
    </body>
        
</html>