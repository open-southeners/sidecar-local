def handler(event, context):
    name = (event or {}).get("name", "world")

    return {
        "message": f"Hello, {name} from Python",
        "runtime": "python3.12",
    }
