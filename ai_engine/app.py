"""Flask API for the AI/ML recommendation engine."""
from flask import Flask, request, jsonify
from flask_cors import CORS

from riasec import score_responses, top_three_code
from model import recommend

app = Flask(__name__)
CORS(app)


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/score", methods=["POST"])
def score():
    """Compute RIASEC scores from raw responses."""
    data = request.get_json(force=True)
    responses = data.get("responses", [])
    scores = score_responses(responses)
    return jsonify({
        "scores": scores,
        "top_code": top_three_code(scores),
    })


@app.route("/recommend", methods=["POST"])
def recommend_endpoint():
    """
    Accepts:
      {
        "responses": [{"dimension":"I","answer":4}, ...],
        "marks": {"maths":70,"science":65,"english":72},
        "subjects": ["Mathematics","Physical Sciences"],
        "aspirations": "I want to build things",
        "top_n": 5
      }
    """
    data = request.get_json(force=True)

    scores = data.get("riasec_scores")
    if not scores:
        scores = score_responses(data.get("responses", []))

    results = recommend(
        riasec_scores=scores,
        marks=data.get("marks", {}),
        subjects=data.get("subjects", []),
        aspirations=data.get("aspirations", ""),
        top_n=int(data.get("top_n", 5)),
    )

    return jsonify({
        "riasec_scores": scores,
        "top_code": top_three_code(scores),
        "recommendations": results,
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=True)