<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Удалить пост?</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
          integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>
<body>


<main role="main" class="container text-center">


    <div>

        <script type="text/javascript" src="https://vk.com/js/api/openapi.js?162"></script>

        <!-- TODO: post preview VK Widget -->
        <div id="vk_groups"></div>
        <script type="text/javascript">
            VK.Widgets.Group("vk_groups", {mode: 3, width: 'auto'}, {{ $group_id }});
        </script>
    </div>

    @if (empty($deleted))
        <form method="POST">
            @csrf
            <div class="form-group">
                Вы действительно хотите удалить
                <a href="https://vk.com/wall-{{ $group_id }}_{{ $post_id }}" target="_blank">пост</a>
                со стены сообщества?
            </div>


            <p>
                <a href="#" onclick="window.close()" class="btn btn-primary">Отмена</a>
            </p>
            <p>
                <button type="submit" class="btn btn-outline-danger">Удалить</button>
            </p>
        </form>
    @else


        <p>
            <a href="https://vk.com/wall-{{ $group_id }}_{{ $post_id }}" target="_blank">Пост</a>
            будет скоро удален!
        </p>
    @endif
</main>

</body>
</html>
